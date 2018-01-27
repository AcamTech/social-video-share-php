<?php

namespace TorCDN\SocialVideoShare;

use Silex;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Extends Silex\Application with a Restful interface
 */
class RestApi extends Application {

  //use Silex\Application\MonologTrait;

  /**
   * Instantiate
   *
   * @param array $config
   *  [
   *    monolog.logfile: '/path/to/file.log',
   *    session: true|false,
   *    debug.requests: true|false
   *  ]
   */
  public function __construct(array $config = []) {
    parent::__construct();

    $this['config'] = $config;

    // enable sessions
    if (isset($config['session'])) {
      $this->register(new Silex\Provider\SessionServiceProvider());
    }

    // register monolog service
    if (isset($config['monolog.logfile'])) {
      $this->register(new Silex\Provider\MonologServiceProvider(), [
        'monolog.logfile' => $config['monolog.logfile'],
      ]);
    }

    // event after routing
    $this->after(function (Request $request, Response $response) use ($config) {

      $route  = $request->getPathInfo();
      $uri    = $request->getUri();
      // debug requests and responses
      if ($route != '/debug' && isset($config['debug.requests']) && $config['debug.requests']) {
        if (isset($this['session'])) {
          $this['monolog']->debug(json_encode([
            'route' => $route,
            'uri' => $uri
          ]));
        }
      }
    });
  }
  
}