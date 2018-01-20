<?php
/**
 * Upload and Share video to Twitter from S3
 */

require_once('vendor/autoload.php');
require_once('lib/Session.class.php');
require_once('lib/Request.class.php');
require_once('lib/S3.stream.class.php');
require_once('lib/TwitterVideoUpload.php');

use TorCDN\S3Stream;
use TorCDN\Twitter\TwitterVideoUpload;
use TorCDN\HttpServerIncomingClientRequest;
use TorCDN\Session;

$Request = new HttpServerIncomingClientRequest();
$Session = new Session('twitter');

// config data
$siteBaseUri = 'http://localhost/codementor/latakoo/social-video-share/';
// S3 
$s3_config = [
    'region' => 'ap-southeast-1',
    'credentials' => [
        'key' => 'AKIAI4LS43SGBDJDZ4RQ',
        'secret' => '5dkwtctkxXn9vvdqXo4AMA+nTFW74Dd/Vl/ah5Ej'
    ]
];
$filename = 'videos/TextInMotion-Sample-576p.mp4';
$bucket = 'torcdn-singapore';
// Twitter
$twitter_config = [
    'key' => '7KJdqkXKVJvUTUMB2oGP8WwzL',
    'secret' => 'sG6LxfJfc7afQGkBS2H550htOXwjttATf38MGUeZJ7n5lF1A7T',
    'callback_url' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
];

$S3Stream = new S3Stream($s3_config);
$Twitter = new TwitterVideoUpload($twitter_config, $Session);
// debugging
$count = 0;
$Twitter->registerDebugHandler(function ($apiMethod, $request, $response = null) use (&$count) {
    // don't debug the raw video data/bytes
    if ($apiMethod == 'media_upload' && isset($request[0]['media'])) {
        $request[0]['media'] = sprintf('{video bytes} of len %s', strlen($request[0]['media']));
    }
    echo '<pre>API Call: ' . $apiMethod  . PHP_EOL
        . 'Request: ' . json_encode($request, JSON_PRETTY_PRINT)  . PHP_EOL
        . 'Response: ' . json_encode($response, JSON_PRETTY_PRINT) . '</pre>' . PHP_EOL;
    ob_get_length() ?: ob_flush();
    flush();
    $count++;
    if ($count >= 50) die;
});

$token = $Twitter->getOAuthToken();

if (!$token) {
    throw new \Exception('Could not get access token.');
}

// we are authenticated
$accessToken = $token['oauth_token'];

if ($Request->get('share')) {
    $videoUrl = $S3Stream->getUrl($bucket, $filename);
    $resp = $Twitter->uploadVideoFromUrl($videoUrl);

    echo '<pre>'; // debug
    echo 'Twitter reply: ' . PHP_EOL;
    var_dump($resp);
} else {
    echo 'Token: <pre>' . $accessToken . '</pre>';
    echo '<a href="?share=1">Share</a> ';
    echo '<a href="?logout=1">Logout</a> ';
}

// debug
if ($Request->isset('logout')) {
    $Session->destroy();
}
