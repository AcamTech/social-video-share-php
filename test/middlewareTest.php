<?php
namespace TorCDN\SocialVideoShare\Test;


use TorCDN\Middleware\Middleware;
use TorCDN\Middleware\Payload;
use TorCDN\Middleware\MiddlewareStack;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Test Middleware for PHP 5.4+
 *
 */
class MiddlewareTest extends TestCase
{

    /**
     * Ensure the middleware can process a payload
     */
    public function testMiddleware()
    {

        $middlewareCalled = false;

        // create a middleware
        $middleware = new Middleware(function($payload, $next) {
          $payload->set('data', 'bar');
          return $next($payload);
        });

        // create arbitrary payload
        $payload = new Payload();
        $payload->set('data', 'foo');

        // add middleware to a stack
        $stack = new MiddlewareStack();
        $stack = $stack->add($middleware);
        $payload = $stack($payload);

        $this->assertTrue($payload->get('data') === 'bar');
        $this->assertTrue($payload->isDirty());

        // run middlware in factory
        $middlewareCallCount = 0;
        $payload = new Payload();
        $middleWare = new MiddleWare(function ($payload, $next) use (&$middlewareCallCount) {
            $middlewareCallCount++;
            return $next($payload);
        });
        $middlewareStack = MiddlewareStack::factory([$middleWare, $middleWare]);

        // executes the middleware
        $middlewareStack($payload);
        
        $this->assertTrue($middlewareCallCount == 2, 'Middleware was was not called.');
    }

}
