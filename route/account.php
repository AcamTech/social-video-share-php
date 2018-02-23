<?php
/**
 * Rest API for User account info
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
 * User Info
 */
$api->get('/account', function (RestApi $api) use ($config) {
  $services = array_keys($config);
  $accounts = array();

  foreach($services as $service) {
    $session = new Session($service);
    $accounts[$service] = $session->getAll();
  }

  $session = new Session('account');

  return $api->json(array(
    'session' => array(
      'cookieDomain' => $session->getCookieDomain(),
      'sessionId' => $session->getId(),
      'data' => $session->getAll(),
      'raw' => $_SESSION
    ),
    'accounts' => $accounts
  ));
});
