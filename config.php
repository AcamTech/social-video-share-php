<?php

// site config
$siteBaseUri = 'http://localhost/codementor/latakoo/';
$siteDomain = parse_url($siteBaseUri)['host'];
$pilotBaseUri = $siteBaseUri;

// FB OAuth configs
$fbConfig = [
  'app_id' => '552377038443474', // Replace {app-id} with your app id
  'app_secret' => '3d3a54b33ff57cbadd165e1d5b09697c',
  'default_graph_version' => 'v2.10',
];

// twitter OAuth configs
$consumerKey = 'LHL2v73VQU4EswJxbt5lHUzFg';
$consumerSecret = 'Xiu1es5wsdunu4H7pWgFF5IuWwQtHZ6inKJ8ljvaURxxuBDJDm';
$redirectURL = $pilotBaseUri;

// google OAuth configs
$google_url = "https://accounts.google.com/o/oauth2/auth";
$google_params = [
	"response_type" => "code",
	"client_id" => "850095945888-2hges0en1jud5genpgt01hfnq3b6ord5.apps.googleusercontent.com",
	"redirect_uri" => $pilotBaseUri,
	"scope" => "https://www.googleapis.com/auth/plus.me"
];