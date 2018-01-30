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
 * @param String appUri    (Optional) Your app URL to return to after authentication. 
 *               Note: API handles OAuth callback. Your App URL only receives the accessToken
 */
$api->get('/facebook/auth', function (RestApi $api, Request $request) use ($config) {

  $returnUri = strtok($request->getUri(), '?');
  $appUri = $request->get('appUri');
  $authUrl = null;

  $Session = new Session('facebook');
  $Facebook = FacebookVideoUploadFactory($config['facebook']);

  $accessToken = $Session->get('accessToken');

  if (!$accessToken) {
    $accessToken = $Facebook->getAccessToken();
    $authUrl = null;
  }

  if (!$accessToken) {
    $authUrl = $Facebook->createAuthUrl($returnUri);
    $Session->set('appUri', $appUri);
  }

  $Session->set('accessToken', $accessToken); 

  // TODO: fix, security of appUri
  $appUri = $Session->get('appUri');
  if ($request->get('code')) {
    return $api->redirect($appUri ? $appUri : $returnUri);
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
 * @param String bucket       (Optional) S3 bucket
 * @param String filename     (Optional) S3 File path
 * @param String url          (Optional) If not using S3 specificy a public video URL
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
  $facebook->setAccessToken($accessToken);

  if (!$url) {
    $url = $S3Stream->getUrl($bucket, $filename);
  }
  $resp = $facebook->uploadVideoFromUrl($meta, $url, $size = null, $progressCallback = null);

  return $api->json($resp);

});

/**
 * REST API endpoint for Vimeo logout
 * @method /twitter/logout
 */
$api->get('/facebook/logout', function (RestApi $api, Request $request) use ($config) {
  $Session = new Session('facebook');
  $Session->destroy();
  return $api->json(['status' => 'Log out ok']);

});

/**
 * Debugging
 */
$api->get('/debug', function (RestApi $api) {
  $resp = file_get_contents($api['config']['monolog.logfile']);
  return new Response($resp, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
});

/**
 * Error handling
 */
$api->error(function (Exception $e) use ($api) {
  $resp = is_callable([$e, 'toJson']) 
    ? $e->toJson() 
    : [
      'error'   => $e->getMessage(),
      'code'    => $e->getCode()
    ];
  return $api->json($resp);
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
    $maxLen = 100;
    $args = func_get_args();
    $logger->debug(stringifyLogValue($args, $maxLen));
  });

  return $facebook;
}

/**
 * Recursively strigify an object with a max value length
 *
 * @param mixed $obj
 * @return void
 */
function stringifyLogValue($obj, $maxLen = 80)
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