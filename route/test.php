<?php
/**
 * Unit tests within REST API Environment
 */

// required by route
use TorCDN\SocialVideoShare\RestApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\Server\Session;
use TorCDN\SocialVideoShare\Exception;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Test sessions data
 */
$api->get('/test/session', function (RestApi $api) {
  $session = new Session('test');
  return $api->json(array(
    'session' => array(
      'cookieDomain' => $session->getCookieDomain(),
      'sessionId' => $session->getId(),
      'data' => $session->getAll(),
      'raw' => $_SESSION
    )
  ));
});

/**
 * Test sessions
 */
$api->get('/test/session/set', function (RestApi $api, Request $request) {

  $data = $request->get('raw');
  foreach($data as $key => $value) {
    $_SESSION[$key] = $value;
  }

  $session = new Session('test');
  
  $data = $request->get('data');
  foreach($data as $key => $value) {
    $session->set($key, $value);
  }

  $data = $request->get('raw');
  foreach($data as $key => $value) {
    $_SESSION['_' . $key] = $value;
  }

  return $api->json(array(
    'session' => array(
      'cookieDomain' => $session->getCookieDomain(),
      'sessionId' => $session->getId(),
      'data' => $session->getAll(),
      'raw' => $_SESSION
    )
  ));
});

/**
 * Test Sessions do not destroy existing session data
 */
$api->get('/test/session/set-not-destroy', function (RestApi $api, Request $request) {

  if (!session_id()) {
    session_start();
  }

  $data = $request->get('raw');
  foreach($data as $key => $value) {
    $_SESSION[$key] = $value;
  }

  $session = new Session('test');
  
  $data = $request->get('data');
  foreach($data as $key => $value) {
    $session->set($key, $value);
  }

  $data = $request->get('raw');
  foreach($data as $key => $value) {
    $_SESSION['_' . $key] = $value;
  }

  return $api->json(array(
    'session' => array(
      'cookieDomain' => $session->getCookieDomain(),
      'sessionId' => $session->getId(),
      'data' => $session->getAll(),
      'raw' => $_SESSION
    )
  ));
});
