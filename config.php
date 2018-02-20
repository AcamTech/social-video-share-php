<?php

use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();

// uri without query params
$scriptUri = strtok($request->getUri(), '?');

$config = [
  'google' => [
    "app_name" => "Delivered by Latakoo",
    "response_type" => "code",
    "client_id" => "287901360233-4dg5thvjnc0svt22gk4crtnv4p9icu0c.apps.googleusercontent.com",
    "client_secret" => "0D4xmzBmB0Cd6ExYQPZ0lDBA",
    "redirect_uri" => $scriptUri,
      "scope" => [
          'https://www.googleapis.com/auth/youtube.force-ssl'
      ]
    ],
  's3' => [
    'region' => 'us-east-1',
    'bucket' => 'pftp-data',
    'credentials' => [
        'key' => 'AKIAIZVH4RMOI6TEYJSA',
        'secret' => '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'
    ]
  ],
  'vimeo' => [
    'client_id' => '665925df8ac28a1d0308da138f7318382a9c0974',
    'client_secret' => 'O+7wWD5NytBRoFcKDUZ57HWq0bqkNlI3XCbPp3nc1wtuve+dNIIZqcOEN3DbjoseUZ5fHNDts4UmuHrooLlkrdTsbV/FrU0maBxNyxOymfoWA2AGtMcZMml5RkDIGKBB',
    'scopes' => ['create', 'upload'], // https://developer.vimeo.com/api/authentication#supported-scope
    'redirect_uri' => $scriptUri
  ],
  'twitter' => [
    'key' => 'pNMGbGGhPIlfqQ4o3n151KwI0',
    'secret' => 'D04R2fhyE0gccFzkApqA3rPKIuoa6EAKVqK546lsBR8dRsM9qO',
    'callback_url' => $scriptUri
  ],
  'facebook' => [
    'app_id' => '248990911853204',
	  'app_secret' => 'd6f8aa2d88bfe629a32a2264dcc22872',
    'default_graph_version' => 'v2.3',
    //'default_access_token' => null, // optional
    'permissions' => [
      'email', 'public_profile', 'publish_actions', 'user_videos'
    ]
  ]
];

return $config;