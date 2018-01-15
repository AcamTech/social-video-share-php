<?php
/**
 * Authenticate Google Accounts
 */

require_once('lib/google.auth.php');

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
	"redirect_uri" => $siteBaseUri . 'google.auth.php',
    "scope" => [
        'https://www.googleapis.com/auth/youtube.force-ssl'
    ]

];

$Client = new Google_Client();
$GoogleAuth = new GoogleAuth($google_params, $Client);

$access_token = $GoogleAuth->getAccessToken();
$authUrl = $GoogleAuth->createAuthUrl();

// redirect to authUrl if we don't have a token yet
if (!$access_token) {
    die(header('Location: ' . $authUrl));
} else {
    echo 'Authenticated! Access Token: <pre>' . print_r($access_token, 1);
}

// debugging
if ($Request->get('logout')) {
    $GoogleAuth->logout();
    die(header('Location: googleplus.share.php'));
}

?>