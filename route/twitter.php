<?php
/**
 * Rest API for Upload and Share video to Twitter from S3 or URL
 */

// TODO: Use InvalidParameterException insted of \Exception(msg, 400)
use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\SocialVideoShare\TwitterVideoUpload;
use TorCDN\Server\Session;
use TorCDN\SocialVideoShare\RestApi;
use TorCDN\SocialVideoShare\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * REST API endpoint for Twitter Authentication
 * @method /twitter/auth
 * @param String returnUri    (Optional) Your app URL to return to after authentication. 
 *               Note: API handles OAuth callback. Your App URL only receives the accessToken
 */
$api->get('/twitter/auth', function (RestApi $api, Request $request) use ($config) {

  $returnUri = $request->get('returnUri');
  if (!$returnUri) {
    $returnUri = strtok($request->getUri(), '?');
  }

  $Twitter = twitterVideoUploadFactory($config['twitter']);

  $token = $Twitter->getOAuthToken($returnUri);
  $authUrl = $token['oauth_token'] ? null : $Twitter->createAuthUrl();

  $resp = [
    'accessToken' => $token['oauth_token'],
    'token'       => $token,
    'authUrl'     => $authUrl
  ];

  return $api->json($resp);
});

/**
 * REST API endpoint for twitter video upload and share
 * @method /twitter/share
 * @param String accessToken  (Optional) Access Token from /twitter/auth
 * @param String bucket       (Optional) S3 bucket
 * @param String filename     (Optional) S3 File path
 * @param String url          (Optional) If not using S3 specificy a public video URL
 */
$api->get('/twitter/share', function (RestApi $api, Request $request) use ($config) {

  $bucket       = $request->get('bucket');
  $filename     = $request->get('filename');
  $url          = $request->get('url');
  $accessToken  = $request->get('accessToken');

  if (!$url && !($bucket || $filename)) {
    throw new \Exception('Required query parameter: url or bucket and filename.', 400);
  }

  $Session = new Session('twitter');
  $S3Stream = new S3Stream($config['s3']);
  $Twitter = twitterVideoUploadFactory($config['twitter']);

  $token = $Twitter->getOAuthToken();
  $Twitter->setToken($accessToken ? $accessToken : $token['oauth_token'], $token['oauth_token_secret']);

  if (!$url) {
    $url = $S3Stream->getUrl($bucket, $filename);
  }
  $resp = $Twitter->uploadVideoFromUrl($url);

  return $api->json($resp);

});

/**
 * REST API endpoint for Vimeo logout
 * @method /twitter/logout
 */
$api->get('/twitter/logout', function (RestApi $api, Request $request) use ($config) {
  $Session = new Session('twitter');
  $Session->destroy();
  return $api->json(['status' => 'Log out ok']);

});

/**
 * Create a TwitterVideoUpload instance
 * @param array $config
 * @return TorCDN\SocialVideoShare\TwitterVideoUpload
 */
function twitterVideoUploadFactory($config)
{
  $Session = new Session('twitter');
  $Twitter = new TwitterVideoUpload($config, $Session);

  $logger = new Logger('TwitterVideoUpload');
  $logger->pushHandler(new StreamHandler('log/twitter.log', Logger::DEBUG));
  $sizeTotal = 0;
  $Twitter->registerDebugHandler(function ($apiMethod, $request, $response = null) use ($logger, &$sizeTotal) {
      // don't debug the raw video data/bytes
      if ($apiMethod == 'media_upload' && $request[0]['command'] = 'APPEND' && isset($request[0]['media'])) {
        $size = strlen($request[0]['media']);
        $sizeTotal += $size;
        $request[0]['media'] = sprintf('{video bytes} chunk length %s total size %s', $size, $sizeTotal);
      }
      $logMsg = '## API Call: ' . $apiMethod  . PHP_EOL
        . 'Request: ' . json_encode($request, JSON_PRETTY_PRINT)  . PHP_EOL
        . 'Response: ' . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;
      $logger->debug($logMsg);
  });

  return $Twitter;
}
