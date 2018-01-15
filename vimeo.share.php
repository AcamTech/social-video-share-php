<?php
/**
 * Upload and Share video to vimeo from S3
 */

require_once('vendor/autoload.php');
require_once('lib/Session.class.php');
require_once('lib/Request.class.php');
require_once('lib/S3.stream.class.php');

use TorCDN\S3Stream;
use TorCDN\VimeoAuth;
use Vimeo\Vimeo;
use TorCDN\HttpServerIncomingClientRequest;
use TorCDN\Session;

$Request = new HttpServerIncomingClientRequest();
$Session = new Session();

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
// Vimeo
$client_id = '4bb96180119c6d1e212922708c2de9f7fac761d6';
$client_secret = 'IiYRQQzf78mnUkXtvb50SnZs/ema7J/HIVNekpu+4mSZpAmojkif0zW9A+g4rrHBCpduTpdCbaXngIMBRCiqcI/3hBYmXZHVdo8Ro67SWIFGx9xBdsuRd4LuN/wsVx8A';
$scopes = ['create', 'upload']; // https://developer.vimeo.com/api/authentication#supported-scopes
$redirect_uri = $siteBaseUri . 'vimeo.share.php';

$S3Stream = new S3Stream($s3_config);
$Vimeo = new Vimeo($client_id, $client_secret);

$token = $Session->get('vimeoToken');
$token = [
    'body' => [ 'access_token' => '6b703223e5ce676e5547e8a6fd51f055' ]
];
if (!$token) {
    $state = generateRandomSecureToken();
    $Session->set('vimeoState', $state);
    $authUrl = $Vimeo->buildAuthorizationEndpoint($redirect_uri, $scopes, $state);
    //header('Location: ' . $authUrl);
    die('<a href="' . $authUrl . '">Login to Vimeo</a>');
}

if ($code = $Request->get('code')) {
    $state = $Request->get('state');
    $oldState = $Session->get('vimeoState');
    $Session->set('vimeoState', null);
    if ($state != $oldState) {
        throw new \Exception('Invalid access token state received.');
    }
    $token = $Vimeo->accessToken($code, $redirect_uri);
}

if (!$token) {
    throw new \Exception('Could not get access token.');
}

// we are authenticated
$accessToken = $token['body']['access_token'];
$accessToken = '6b703223e5ce676e5547e8a6fd51f055'; // TODO: remove, dev only
$Vimeo->setToken($accessToken);

if ($Request->get('share')) {
    $videoUrl = $S3Stream->getUrl($bucket, $filename);
    $resp = $Vimeo->request('/me/videos', array('type' => 'pull', 'link' => $videoUrl), 'POST');

    echo '<pre>'; // debug
    var_dump($resp);
} else {
    echo 'Token: <pre>' . $accessToken . '</pre>';
    echo '<a href="?share=1">Share</a>';
}

function generateRandomSecureToken($length = 40) {
    $chars = [];
    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
    $codeAlphabet.= "0123456789";
    $max = strlen($codeAlphabet);

   for ($i=0; $i < $length; $i++) {
       $chars[] = $codeAlphabet[random_int(0, $max-1)];
   }

   return implode('', $chars);
}
