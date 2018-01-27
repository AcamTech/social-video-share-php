<?php
/**
 * REST API for social networks video upload and share
 */

require_once __DIR__ . '/vendor/autoload.php';

use TorCDN\SocialVideoShare\RestApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\SocialVideoShare\GoogleAuth;
use TorCDN\SocialVideoShare\YoutubeVideoUpload;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

ini_set('display_errors', 0);

$config = require __DIR__ . '/config.php';
$api = new RestApi([
  'monolog.logfile' => __DIR__ . '/rest-api.log',
  'session'         => true,
  'debug.requests'  => true
]);

/**
 * Authenticate with Google OAuth
 */
$api->get('/google/auth', function (RestApi $api, Request $request) use ($config) {

  $Client = new Google_Client();
  $GoogleAuth = new GoogleAuth($config['google'], $Client, $api['session']);

  $accessToken = $GoogleAuth->getAccessToken();
  $authUrl = $GoogleAuth->createAuthUrl($request->get('redirectUrl'));

  $resp = [
    'accessToken' => $accessToken,
    'authUrl' => $authUrl
  ];

  return $api->json($resp);
});

/**
 * Upload and share to youtube
 */
$api->get('/youtube/share', function (RestApi $api, Request $request) use ($config) {
  // execution time limit not exceeding execTimeLimitMax
  $execTimeLimitMax = 60 * 60; // 1 hour
  $execTimeLimit = min($request->get('execTimeLimit'), $execTimeLimitMax);

  $bucket = $request->get('bucket');
  $filename = $request->get('filename');
  $url = $request->get('url');
  $accessToken = $request->get('accessToken');

  if (!$url && !($bucket || $filename)) {
    throw new \Exception('Required query parameter: url or bucket and filename.');
  }
  if (!$accessToken) {
    throw new \Exception('Required query parameter: accessToken.');
  }

  $autoTitle = $filename ?: basename(strtok($url, '?'));
  $title = $request->get('title') ?: $autoTitle;
  $description = $request->get('description');
  $categoryId = $request->get('categoryId');
  $privacyStatus = $request->get('privacyStatus') ?: 'public';
  
  $Client = new Google_Client();
  $Client->setAccessToken($accessToken);
  $YoutubeVideoUpload = new YoutubeVideoUpload($Client);
  $logger = new Logger('YoutubeVideoUpload');
  $logger->pushHandler(new StreamHandler('./YoutubeVideoUpload.log', Logger::DEBUG));
  $this->setLogger($logger);
  $S3Stream = new S3Stream($config['s3']);

  if (!$url) {
    $url = $S3Stream->getUrl($bucket, $filename);
  }
  $headers = $S3Stream->getUrlHeaders($url);
  $length = isset($headers['Content-Length']) ? (int) $headers['Content-Length'][0] : null;

  // max script execution time (15 mins)
  $YoutubeVideoUpload->setTimeLimit($execTimeLimit ?: $execTimeLimitMax);
  
  // category https://developers.google.com/youtube/v3/docs/videoCategories/list
  $videoMeta = [
    "snippet"=>
      [
        "categoryId"    => $categoryId ?: 22,
        "description"   => $description,
        "title"         => $title
      ],
    "status" =>
      [
        "privacyStatus" => $privacyStatus
      ]
  ];
  $status = $YoutubeVideoUpload->uploadVideoFile(
    $url,
    $videoMeta,
    'snippet,status', array(), $length);

    if ($status) {
      throw new \Exception('Youtube upload failed with status: ' . $status);
    }
    $resp = array_merge($videoMeta, [
      'length' => $length,
      'url'     => $url
    ]);

  return $api->json($resp);
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
$api->error(function (\Exception $e, Request $request, $code) use ($api) {
  $resp = [
    'error'   => $e->getMessage(),
    'code'    => $code,
    'request' => $request
  ];
  return $api->json($resp);
});

$api->run();