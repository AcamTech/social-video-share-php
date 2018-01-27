<?php

use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();

// uri without query params
$scriptUri = strtok($request->getUri(), '?');

$config = [
  'google' => [
    "app_name" => "Latakoo",
    "response_type" => "code",
    "client_id" => "850095945888-2hges0en1jud5genpgt01hfnq3b6ord5.apps.googleusercontent.com",
    "client_secret" => "Qlavzeo3LyF98fnQeVXBnStE",
    "redirect_uri" => $scriptUri,
      "scope" => [
          'https://www.googleapis.com/auth/youtube.force-ssl'
      ]
    ],
  's3' => [
    'region' => 'ap-southeast-1',
    'credentials' => [
        'key' => 'AKIAI4LS43SGBDJDZ4RQ',
        'secret' => '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'
    ]
  ]
];

return $config;