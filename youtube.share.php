<?php
/**
 * Upload video from Amazon S3 to Youtube
 */

require_once __DIR__ . '/vendor/autoload.php';

use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\SocialVideoShare\GoogleAuth;
use TorCDN\SocialVideoShare\YoutubeVideoUpload;
use TorCDN\Server\Request;
use TorCDN\Server\Session;

$Request = new Request();

// config data
$siteBaseUri = 'http://localhost/';
// google client
$google_params = [
	"app_name" => "Latakoo",
	"response_type" => "code",
	"client_id" => "850095945888-2hges0en1jud5genpgt01hfnq3b6ord5.apps.googleusercontent.com",
	"client_secret" => "Qlavzeo3LyF98fnQeVXBnStE",
	"redirect_uri" => $siteBaseUri . 'RestApi.php/google/auth',
    "scope" => [
        'https://www.googleapis.com/auth/youtube.force-ssl'
    ]
];
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

$Client = new Google_Client();
$Session = new Session('google');
$GoogleAuth = new GoogleAuth($google_params, $Client, $Session);
$S3Stream = new S3Stream($s3_config);
$YoutubeVideoUpload = new YoutubeVideoUpload($Client);

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
if ($share) {
    if ($access_token) {

        $url = $S3Stream->getUrl($bucket, $filename);
        $headers = $S3Stream->getHeaders($bucket, $filename);
        $length = isset($headers['Content-Length']) ? (int) $headers['Content-Length'][0] : null;

        // tests
        //$url = './video/SampleVideo_720x480_1mb.mp4';
        //$url = '../SampleVideo_1280x720_2mb.mp4';

        // max script execution time (15 mins)
        ini_set('max_execution_time', 60 * 2);
        $YoutubeVideoUpload->setTimeLimit(60 * 2);    
        
        // category https://developers.google.com/youtube/v3/docs/videoCategories/list
        $YoutubeVideoUpload->uploadVideoFile(
            $url,
            [
                "snippet"=>
                    [
                    "categoryId"    =>"22",
                    "description"   => "Test S3 to Youtube upload",
                    "title"         => "Test S3 video upload @ " . date('r')
                    ],
                "status" =>
                    [
                        "privacyStatus" => "public"
                    ]
            ],
            'snippet,status', array(), $length);

        echo 'Wrote file (' . $url . ') to youtube';
    } else {
        echo '<h3>Please login before sharing.</h3>';
    }
    
}