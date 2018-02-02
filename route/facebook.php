<?php
/**
 * Rest API for Upload and Share video to facebook from S3 or URL
 */

// required by route
use TorCDN\SocialVideoShare\RestApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\SocialVideoShare\FacebookVideoUpload;
use TorCDN\Server\Session;
use TorCDN\SocialVideoShare\Exception;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * REST API endpoint for facebook Authentication
 * @method /facebook/auth
 * @param String appUrl    (Optional) Your app URL to return to after authentication. 
 *               Note: API handles OAuth callback. Your App URL only receives the accessToken
 */
$api->get('/facebook/auth', function (RestApi $api, Request $request) use ($config) {

  $returnUri  = strtok($request->getUri(), '?');
  $appUrl     = $request->get('appUrl');
  $authUrl    = null;

  $Session = new Session('facebook');
  $Facebook = FacebookVideoUploadFactory($config['facebook']);

  $accessToken = $Session->get('accessToken');

  if (!$accessToken) {
    $accessToken = $Facebook->getAccessToken();
  }

  if (!$accessToken) {
    $authUrl = $Facebook->createAuthUrl($returnUri);
    $Session->set('appUrl', $appUrl);
  }

  $Session->set('accessToken', $accessToken);

  // TODO: fix, security of appUrl
  $appUrl = $Session->get('appUrl');
  if ($request->get('code')) {
    return $api->redirect($appUrl ? $appUrl : $returnUri);
  }

  $resp = [
    'accessToken' => $accessToken,
    'authUrl'     => $authUrl
  ];

  return $api->json($resp);
});

/**
 * REST API endpoint for facebook video upload and share
 * @method /facebook/share
 * @param String accessToken  (Optional) Access Token from /facebook/auth
 * 
 * If using S3
 * @param String bucket       S3 bucket
 * @param String filename     S3 File path
 * 
 * If using a remote URL
 * @param String url          Public video URL
 */
$api->get('/facebook/share', function (RestApi $api, Request $request) use ($config) {

  $bucket       = $request->get('bucket');
  $filename     = $request->get('filename');
  $url          = $request->get('url');
  $accessToken  = $request->get('accessToken');
  $meta         = [
                    'title' => $request->get('title'),
                    'description' => $request->get('description')
                  ];

  if (!$url && !($bucket || $filename)) {
    throw new \Exception('Required query parameter: url or bucket and filename.', 400);
  }

  $Session = new Session('facebook');
  $S3Stream = new S3Stream($config['s3']);
  $facebook = facebookVideoUploadFactory($config['facebook']);

  if (!$accessToken) {
    $accessToken = $Session->get('accessToken');
  }

  if (!$accessToken) {
    throw new \Exception('Required query parameter: accessToken. Call /facebook/auth to generate one.', 400);
  }

  $facebook->setAccessToken($accessToken);

  if (!$url) {
    $url = $S3Stream->getUrl($bucket, $filename);
  }
  $resp = $facebook->uploadVideoFromUrl($meta, $url, $size = null, $progressCallback = null);

  return $api->json($resp);

});

/**
 * REST API endpoint for Facebook logout
 * @method /facebook/logout
 */
$api->get('/facebook/logout', function (RestApi $api) {
  $Session = new Session('facebook');
  $Session->destroy();
  return $api->json(['status' => 'Log out ok']);
});

/**
 * Generate a logger singleton
 * @return Monolog\Logger
 */
function facebookLoggerFactory()
{
  static $logger;
  if (!$logger) {
    $logger = new Logger('FacebookVideoUpload');
    $logger->pushHandler(new StreamHandler('log/facebook.log', Logger::DEBUG));
  }
  return $logger;
}

/**
 * Create a facebookVideoUpload instance
 * @param array $config
 * @return TorCDN\SocialVideoShare\facebookVideoUpload
 */
function facebookVideoUploadFactory($config)
{
  $Session = new Session('facebook');
  $facebook = new FacebookVideoUpload($config, $Session);

  $logger = facebookLoggerFactory();
  $facebook->setLogger(function() use ($logger) {
    $args = func_get_args();
    $logger->debug(stringifyLogValue($args));
  });

  return $facebook;
}

/**
 * Recursively strigify an object with a max value length
 *
 * @param mixed $obj
 * @return void
 */
function stringifyLogValue($obj, $maxLen = 200)
{
  $str = '';
  if (is_scalar($obj)) {
    $len = strlen($obj);
    $str = $len > $maxLen ? "{{length:$len}}" : $obj;
  } else if (is_array($obj)) {
    $lines = [];
    foreach($obj as $key => $value) {
      $lines[] = $key . ': ' . stringifyLogValue($value);
    }
    $str .= '{' . implode(', ', $lines) . '}';
  } else {
    $str = json_encode($obj); 
  }
  return $str;
}