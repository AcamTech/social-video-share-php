<?php
/**
 * REST API for social networks video upload and share
 */

require_once __DIR__ . '/vendor/autoload.php';

use TorCDN\SocialVideoShare\RestApi;
use TorCDN\Server\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// TODO: remove
ini_set('display_errors', false);
error_reporting(E_ALL);

$config = require __DIR__ . '/config.php';

$api = new RestApi([
  'monolog.logfile' => __DIR__ . '/log/rest-api.log',
  'session'         => true,
  'debug.requests'  => true
]);

// cors
$api->after(function (Request $request, Response $response) {
  $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_REFERER'];
  $response->headers->set('Access-Control-Allow-Origin', $origin);
  $response->headers->set('Access-Control-Allow-Credentials', 'true');
  $response->headers->set('Access-Control-Allow-Headers', '*');
  $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT');
});

$api->options("{anything}", function () {
  return new \Symfony\Component\HttpFoundation\JsonResponse(null, 204);
})->assert("anything", ".*");

// routes
require_once __DIR__ . '/route/google.php';
require_once __DIR__ . '/route/vimeo.php';
require_once __DIR__ . '/route/twitter.php';
require_once __DIR__ . '/route/facebook.php';

/**
 * User Info
 * @todo implement
 */
$api->get('/account', function (RestApi $api) use ($config) {
  $services = array_keys($config);
  $accounts = array();

  foreach($services as $service) {
    $session = new Session($service);
    $accounts[$service] = $session->getAll();
  }

  $session = new Session();

  return $api->json(array(
    'session' => array(
      'cookieDomain' => $session->getCookieDomain(),
      'sessionId' => $session->getId()
    ),
    'accounts' => $accounts
  ));
});

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
$api->error(function (Exception $e) use ($api) {
  $resp = is_callable([$e, 'toJson']) 
    ? $e->toJson() 
    : [
      'error'   => $e->getMessage(),
      'code'    => $e->getCode()
    ];
  return $api->json($resp);
});

$api->run();