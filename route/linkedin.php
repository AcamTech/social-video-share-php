<?php
/**
 * Rest API for Upload and Share video to Linkedin from S3
 */

use TorCDN\SocialVideoShare\RestApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\SocialVideoShare\LinkedinVideoUpload;
use TorCDN\Server\Session;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Happyr\LinkedIn\LinkedIn;

/**
 * Factory to instantiate linkedin client
 * 
 * @param $config[app_id] App Id
 * @param $config[app_secret] App secret
 * 
 * @return LinkedIn
 */
function createLinkedinApiClient(array $config)
{
  $Client = new LinkedIn($config['app_id'], $config['app_secret']);
  $Client->setHttpClient(new \Http\Adapter\Guzzle6\Client());
  $Client->setHttpMessageFactory(new \Http\Message\MessageFactory\GuzzleMessageFactory());
  return $Client;
}

/**
 * REST API endpoint for Linkedin Authentication
 * @method /linkedin/auth
 * @param String appUrl    (Optional) Your app URL. Will be appended with ?accessToken={accessToken}
 */
$api->get('/linkedin/auth', function (RestApi $api, Request $request) use ($config) {

  $clientConfig = $config['linkedin'];

  $appUrl      = $request->get('appUrl');
  $redirectUrl = strtok($request->getUri(), '?');

  $Session     = new Session('linkedin');
  $Client      = createLinkedinApiClient(array(
    'app_id'      => $clientConfig['app_id'],
    'app_secret'  => $clientConfig['app_secret']
  ));

  //$isAuthed      = $Client->isAuthenticated(); // we don't have profile perms so this fails.
  $accessToken = $Client->getAccessToken();
  $authUrl     = $Client->getLoginUrl(array('scope' => $clientConfig['scope']));

  if ($accessToken) {
    $Session->set('accessToken', $accessToken->getToken());
  } elseif ($Client->hasError()) {
    throw new \Exception('User canceled linkedin authentication');
  }

  if ($appUrl) {
    $Session->set('appUrl', $appUrl);
  }

  if ($request->get('code') && $request->get('state')) {
    return $api->redirect($appUrl ? $appUrl : $returnUri);
  }

  $resp = [
    'appUrl'      => $Session->get('appUrl'),
    'accessToken' => $accessToken ? $accessToken->getToken() : null,
    'expiresAt'   => $accessToken ? $accessToken->getExpiresAt() : null,
    'authUrl'     => $authUrl,
    'session'     => $Session->getAll()
  ];

  return $api->json($resp);
});

/**
 * Upload and share to linkedin
 * @method /linkedin/share
 * 
 * If using a URL
 * @param $url Publically accessible URL of video
 * 
 * If using S3
 * @param $bucket S3 Bucket
 * @param $filename S3 file path
 */
$api->get('/linkedin/share', function (RestApi $api, Request $request) use ($config) {

  $clientConfig = $config['linkedin'];
  
  $Session      = new Session('linkedin');

  $bucket       = $request->get('bucket');
  $filename     = $request->get('filename');
  $url          = $request->get('url');
  $accessToken  = $request->get('accessToken') ?: $Session->get('accessToken');

  if (!$url && !($bucket || $filename)) {
    throw new \Exception('Required query parameter: url or bucket and filename.');
  }
  if (!$accessToken) {
    throw new \Exception('Required query parameter: accessToken.');
  }

  $autoTitle      = $filename ?: basename(strtok($url, '?'));
  $title          = $request->get('title') ?: $autoTitle;
  $description    = $request->get('description');
  $privacyStatus  = $request->get('privacyStatus') ?: 'anyone';
  $thumbnail      = $request->get('thumbnail');
  
  $logger = new Logger('LinkedinVideoUpload');
  $logger->pushHandler(new StreamHandler('log/linkedin.log', Logger::DEBUG));

  $Client      = createLinkedinApiClient(array(
    'app_id'      => $clientConfig['app_id'],
    'app_secret'  => $clientConfig['app_secret']
  ));

  $Client->setAccessToken($accessToken);

  $S3Stream = new S3Stream($config['s3']);
  $S3Stream->setLogger(function() use ($logger) {
    $logger->debug('S3 Debug: ' . json_encode(func_get_args(), JSON_PRETTY_PRINT));
  });

  if (!$url) {
    $url = $S3Stream->getUrl($bucket, $filename);
  }

  // monitor the upload
  $Client->setAccessToken($Session->get('accessToken'));

  $options = array('json'=>
    array(
      'content' => array(
        'submitted-url' => $url,
        'submitted-image-url' => $thumbnail
      ),
      'comment' => ($description ?: $title),
      'visibility' => array(
        'code' => $privacyStatus
      )
    )
  );

  $logger->debug('Posting to linkedin', $options);

  $resp = $Client->post('v1/people/~/shares', $options);

  $logger->debug('Linkedin post response', $options);

  return $api->json($resp);
});

/**
 * REST API endpoint for Linkedin logout
 * @method /linkedin/logout
 */
$api->get('/linkedin/logout', function (RestApi $api) {
  $Session = new Session('linkedin');
  $Session->destroy();
  return $api->json(['status' => 'Log out ok']);
});