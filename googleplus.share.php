<?php

require_once('lib/google.auth.php');
require_once('lib/S3.stream.class.php');

use TorCDN\S3Stream;
use TorCDN\GoogleAuth;
use TorCDN\HttpServerIncomingClientRequest;

$Request = new HttpServerIncomingClientRequest();

// config data
$siteBaseUri = 'http://localhost/codementor/latakoo/social-video-share/';
// google client
$google_params = [
	"app_name" => "Latakoo",
	"response_type" => "code",
	"client_id" => "850095945888-2hges0en1jud5genpgt01hfnq3b6ord5.apps.googleusercontent.com",
	"client_secret" => "Qlavzeo3LyF98fnQeVXBnStE",
	"redirect_uri" => $siteBaseUri . 'googleplus.share.php',
    "scope" => [
        'https://www.googleapis.com/auth/plus.me',
        'https://www.googleapis.com/auth/plus.media.upload',
        'https://www.googleapis.com/auth/plus.stream.write'
    ]

];

$Client = new Google_Client();
$GoogleAuth = new GoogleAuth($google_params, $Client);
$GooglePlus = new Google_Service_Plus($Client);
$GooglePlusDomains = new Google_Service_PlusDomains($Client);

// debugging
if ($Request->get('logout')) {
    $GoogleAuth->logout();
    die(header('Location: googleplus.share.php'));
}

$access_token = $GoogleAuth->getAccessToken();
$authUrl = $GoogleAuth->createAuthUrl();

?>

<?php if ($access_token) { ?>
	<p><span>Access Token:</span> <?php echo $access_token['access_token']; ?></p>
    <form>
        <button name="share" value="1">Share</button>
    </form>
<?php } else { ?>
	<a href="<?php echo $authUrl; ?>">Connect</a>
<?php } ?>

<?php 

$share = $Request->get('share');

// s3
$s3_config = [
    'region' => 'ap-southeast-1',
    'credentials' => [
        'key' => 'AKIAI4LS43SGBDJDZ4RQ',
        'secret' => '5dkwtctkxXn9vvdqXo4AMA+nTFW74Dd/Vl/ah5Ej'
    ]
];
$filename = 'videos/TextInMotion-Sample-576p.mp4';
$bucket = 'torcdn-singapore';

$S3Stream = new S3Stream($s3_config);

// Google+
$userId = 'me';
$collection = 'cloud'; // public

echo '<pre>'; // debug

// if we have a token
if ($share) {
    if ($access_token) {
        $url = $S3Stream->getUrl($bucket, $filename);

        $postBody = new Google_Service_PlusDomains_Media();
        $postBody->setDisplayName('video.mp4');
        $stream = new Google_Service_PlusDomains_Videostream();
        $stream->setUrl($url);
        //$postBody->setStreams($stream);
        $postBody->setUrl($url);
        $GooglePlusDomains->media->insert($userId, $collection, $postBody);

        echo 'Wrote ' . $len . ' bytes to video.mp4';
    } else {
        echo '<h3>Please login before sharing.</h3>';
    }
    
}
