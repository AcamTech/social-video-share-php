<?php

/*
 * Test the exposed API
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
class RestApiTest extends TestCase
{
    private $http;
    
    public function setUp()
    {
        $this->http = new GuzzleHttp\Client([
            'base_uri' => config::base_uri,
            'cookies'  => true
        ]);
        //`php -S localhost:8080 -t ../`;
    }

    public function tearDown()
    {
        $this->http = null;
    }

    public function testApiConstruct()
    {
        $api = new RestApi();
        $this->assertTrue($api instanceof Application);
    }
    
    public function testAccount()
    {
        // session created
        $resp = $this->http->get('/restApi.php/account', ['stream' => true]);
        $this->assertEquals(200, $resp->getStatusCode());
        $contentType = $resp->getHeaders()["Content-Type"][0];
        $this->assertEquals("application/json", $contentType);
        $body = Utils::getJsonFromStream($resp->getBody());
        $this->assertTrue(isset($body->session->sessionId));

        // using the same session
        $resp2 = $this->http->get('/restApi.php/account', ['stream' => true]);
        $body2 = Utils::getJsonFromStream($resp2->getBody());
        $this->assertEquals($body->session->sessionId, $body2->session->sessionId);
 
    }

    public function testFacebookAuth()
    {
        $resp = $this->http->get('/restApi.php/facebook/auth', ['stream' => true]);
        $this->assertEquals(200, $resp->getStatusCode());
        $contentType = $resp->getHeaders()["Content-Type"][0];
        $this->assertEquals("application/json", $contentType);
        $body = Utils::getJsonFromStream($resp->getBody());
        $this->assertTrue(!empty($body->authUrl));
        
    }

}
