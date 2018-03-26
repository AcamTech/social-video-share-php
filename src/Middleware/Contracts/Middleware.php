<?php

namespace TorCDN\Middleware\Contracts;

use TorCDN\Middleware\MiddlewareStack;
use TorCDN\Middleware\Payload;

/**
 * Process a payload
 */
interface Middleware
{
    /**
     *  Process a payload, optionally delegating parsing to the $next MiddlewareStack
     *
     * @param Payload $payload
     * @param MiddlewareStack $next
     *
     * @return Payload
     */
    public function __invoke(Payload $payload, MiddlewareStack $next);
}
