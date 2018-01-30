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
  ],
  'vimeo' => [
    'client_id' => '4bb96180119c6d1e212922708c2de9f7fac761d6',
    'client_secret' => 'IiYRQQzf78mnUkXtvb50SnZs/ema7J/HIVNekpu+4mSZpAmojkif0zW9A+g4rrHBCpduTpdCbaXngIMBRCiqcI/3hBYmXZHVdo8Ro67SWIFGx9xBdsuRd4LuN/wsVx8A',
    'scopes' => ['create', 'upload'], // https://developer.vimeo.com/api/authentication#supported-scope
    'redirect_uri' => $scriptUri
  ],
  'twitter' => [
    'key' => '7KJdqkXKVJvUTUMB2oGP8WwzL',
    'secret' => 'sG6LxfJfc7afQGkBS2H550htOXwjttATf38MGUeZJ7n5lF1A7T',
    'callback_url' => $scriptUri
  ],
  'facebook' => [
    'app_id' => '552377038443474',
    'app_secret' => '3d3a54b33ff57cbadd165e1d5b09697c',
    'default_graph_version' => 'v2.11',
    'default_access_token' => null, // optional
    'permissions' => [
      'email', 'publish_actions', 'videos'
    ]
  ]
];

return $config;