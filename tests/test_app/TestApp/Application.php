<?php
declare(strict_types=1);

namespace App;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use LinkScanner\Plugin as LinkScanner;

class Application extends BaseApplication
{
    public function bootstrap(): void
    {
        $this->addPlugin(LinkScanner::class);
    }

    public function middleware($middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue->add(new RoutingMiddleware($this));
    }
}
