<?php

/*
 * Test the exposed API
 */

namespace TorCDN\SocialVideoShare\Test;

use TorCDN\SocialVideoShare\Api;
use PHPUnit\Framework\TestCase;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\Api\ControllerProviderInterface;
use Silex\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\WebLink\HttpHeaderSerializer;

/**
 * API test cases.
 *
 * @author Gabe Lalasava <gabe@torcdn.com>
 */
class RestApiTest extends TestCase
{
    public function testApiConstruct()
    {
        $api = new Api();

        // TODO: Implement tests
        throw new \Exception('Test not yet implemented');
    }
}
