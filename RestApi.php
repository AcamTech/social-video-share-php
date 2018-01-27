<?php
/**
 * REST API for social networks video upload and share
 */

require_once __DIR__ . '/vendor/autoload.php';

use TorCDN\SocialVideoShare\RestApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// TOOD: remove
ini_set('display_errors', true);
error_reporting(E_ALL && ~E_NOTICE);

$config = require __DIR__ . '/config.php';
$api = new RestApi([
  'monolog.logfile' => __DIR__ . '/rest-api.log',
  'session'         => true,
  'debug.requests'  => true
]);

// routes
require_once __DIR__ . '/route/google.php';

/**
 * Debugging
 */
$api->get('/debug', function (RestApi $api) {
  $resp = file_get_contents($api['config']['monolog.logfile']);
  return new Response($resp, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
});

/**
 * Error handling
 */
$api->error(function (Exception $e, $code) use ($api) {
  $resp = [
    'error'   => $e->getMessage(),
    'code'    => $code
  ];
  return $api->json($resp);
});

$api->run();