<?php

namespace TorCDN\Middleware;

use TorCDN\Middleware\Contracts;

/**
 * Wraps a callable as a Middleware
 */
class Middleware implements Contracts\Middleware
{
    /**
     * Create a middleware using a callable $fn
     *
     * @param callable $fn
     */
    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    /**
     * Process payload, optionally delegating processing to the $next MiddlewareStack
     * 
     * @param array $payload payload
     * @param MiddlewareStack $next Next MiddlewareStack
     */
    public function __invoke(Payload $payload, MiddlewareStack $next)
    {
        return call_user_func($this->fn, $payload, $next);
    }
}
