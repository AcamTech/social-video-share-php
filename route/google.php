<?php
/**
 * Rest API for Upload and Share video to Youtube from S3
 */

use TorCDN\SocialVideoShare\RestApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\SocialVideoShare\GoogleAuth;
use TorCDN\SocialVideoShare\YoutubeVideoUpload;
use TorCDN\Server\Session;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * REST API endpoint for Google Authentication
 * @method /google/auth
 * @param String returnUri    (Optional) Your app URL. Will be appended with ?accessToken={accessToken}
 * @param String redirectUri  (Optional) Defaults to absolute URL to /vimeo/auth route
 */
$api->get('/google/auth', function (RestApi $api, Request $request) use ($config) {

  $redirectUrl = $request->get('redirectUrl');

  $Client = new Google_Client();
  $GoogleAuth = new GoogleAuth($config['google'], $Client, new Session('google'));

  $accessToken = $GoogleAuth->getAccessToken();
  $authUrl = $GoogleAuth->createAuthUrl($redirectUrl);

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
  $logger->pushHandler(new StreamHandler('log/youtube.log', Logger::DEBUG));
  $YoutubeVideoUpload->setLogger($logger);
  $S3Stream = new S3Stream($config['s3']);
  $S3Stream->setLogger(function() use ($logger) {
    $logger->debug('S3 Debug: ' . json_encode(func_get_args(), JSON_PRETTY_PRINT));
  });

  if (!$url) {
    $url = $S3Stream->getUrl($bucket, $filename);
  }

  $headers = $S3Stream->getUrlHeaders($url);
  $length = isset($headers['Content-Length']) ? (int) $headers['Content-Length'][0] : null;

  // max script execution time (15 mins)
  $YoutubeVideoUpload->setTimeLimit($execTimeLimit ?: $execTimeLimitMax);

  // monitor the upload
  $upload_progress = [];
  $YoutubeVideoUpload->setProgresshandler(function($progress) use ($logger, &$upload_progress) {
    $upload_progress[] = $progress;
    $logger->debug('Youtube Progress: ' . json_encode($progress, JSON_PRETTY_PRINT));
  });
  
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

  $logger->debug('Youtube Upload: ' . json_encode([
    'url' => json_encode($url, JSON_PRETTY_PRINT),
    'meta' => json_encode($videoMeta, JSON_PRETTY_PRINT),
    'length' => json_encode($length, JSON_PRETTY_PRINT)
  ], JSON_PRETTY_PRINT));

  // $file_path, $properties, $part, $params, $length = null
  $status = $YoutubeVideoUpload->uploadVideoFile(
    $url,
    $videoMeta,
    'snippet,status', 
    array(), 
    $length
  );

  if (!$status) {
    //throw new \Exception('Youtube upload failed with null status.');
  }

  $resp = [
    'upload' => $videoMeta,
    'length' => $length,
    'url'     => $url,
    'status' => $status,
    'upload_progress' => $upload_progress
  ];

  return $api->json($resp);
});