<?php

namespace TorCDN\Middleware;

/**
 * A stack of middleware chained together by (MiddlewareStack $next)
 */
class MiddlewareStack
{
    /**
     * Next MiddlewareStack in chain
     *
     * @var MiddlewareStack
     */
    protected $next;

    /**
     * Middleware in this MiddlewareStack
     *
     * @var Middleware
     */
    protected $middleware;

    /**
     * Construct the first middleware in this MiddlewareStack
     * The next middleware is chained through $MiddlewareStack->add($Middleware)
     *
     * @param Middleware $middleware
     */
    public function __construct(Middleware $middleware = null)
    {
        $this->middleware = $middleware;
    }

    /**
     * Creates a chained middleware in MiddlewareStack
     *
     * @param Middleware $middleware
     * @return MiddlewareStack Immutable MiddlewareStack
     */
    public function add(Middleware $middleware)
    {
        $stack = new static($middleware);
        $stack->next = $this;
        return $stack;
    }

    /**
     * Creates a MiddlewareStack based on an array of middleware
     *
     * @param Middleware[] $middlewares
     * @return MiddlewareStack
     */
    public static function factory(array $middlewares = array())
    {
        $stack = new static;
        foreach ($middlewares as $middleware) {
            $stack = $stack->add($middleware);
        }
        return $stack;
    }

    /**
     * Processes the Payload by passing it through all the Middleware[] in stack
     * @param Payload $payload
     * @return Payload
     */
    public function __invoke(Payload $payload)
    {
        if (!$this->middleware) {
            return $payload;
        }
        $payload = call_user_func($this->middleware, $payload, $this->next);
        return $payload;
    }
}
