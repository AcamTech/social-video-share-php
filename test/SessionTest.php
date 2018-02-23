<?php

/*
 * Test the Sessions within REST API
 */

namespace TorCDN\SocialVideoShare\Test;

use TorCDN\SocialVideoShare\RestApi;
use PHPUnit\Framework\TestCase;
use Silex\Application;
use TorCDN\SocialVideoShare\S3Stream;
use GuzzleHttp;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

if (isset($config)) {
    $GLOBALS['config'] = $config;
}

/**
 * API test cases.
 *
 * @author Gabe Lalasava <gabe@torcdn.com>
 * 
 * @example Start Server using: php -S localhost:8080
 * ./vendor/bin/phpunit test/
 * 
 * @todo Fix https://github.com/guzzle/guzzle/issues/1973
 */
class SessionTest extends TestCase
{
    private $http;
    
    public function setUp()
    {
        $this->http = new GuzzleHttp\Client([
            'base_uri' => config::base_uri,
            'cookies'  => true
        ]);
    }

    public function tearDown()
    {
        $this->http = null;
    }
    
    public function testSession()
    {
        $rand = mt_rand();

        // new session created
        $resp = $this->http->get('/restApi.php/test/session', ['stream' => true]);
        $body = Utils::getJsonFromStream($resp->getBody());
        $this->assertTrue(isset($body->session->sessionId));
        $this->assertTrue(!isset($body->session->data->foo)); // data not set
        $this->assertTrue(!isset($body->session->raw->bar)); // data not set

        // save to session
        $resp = $this->http->get('/restApi.php/test/session/set', [
            'stream' => true,
            'query'  => [
                'data[foo]' => $rand,
                'raw[bar]' => $rand
            ]
        ]);
        $body2 = Utils::getJsonFromStream($resp->getBody());
        $this->assertEquals($body->session->sessionId, $body2->session->sessionId);

        // get from session
        $resp = $this->http->get('/restApi.php/test/session', ['stream' => true]);
        $body3 = Utils::getJsonFromStream($resp->getBody());
        $this->assertEquals($body3->session->data->foo, $body2->session->data->foo);
        $this->assertEquals($body3->session->raw->_bar, $body2->session->raw->_bar);

        // save to session when another session already started 
        // doesn't destroy existing session
        $resp = $this->http->get('/restApi.php/test/session/set-not-destroy', [
            'stream' => true,
            'query'  => [
                'data[foo]' => $rand,
                'raw[bar]' => $rand
            ]
        ]);
        $body4 = Utils::getJsonFromStream($resp->getBody());

        $resp = $this->http->get('/restApi.php/test/session', ['stream' => true]);
        $body5 = Utils::getJsonFromStream($resp->getBody());

        $this->assertEquals($body4->session->raw->bar, $body5->session->raw->bar);
        $this->assertEquals($body4->session->raw->_bar, $body5->session->raw->bar);
 
    }

}
