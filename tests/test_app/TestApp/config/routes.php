<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

Router::defaultRouteClass(DashedRoute::class);

/** @var \Cake\Routing\RouteBuilder $routes */
$routes->scope('/', function (RouteBuilder $routes) {
    $routes->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
    $routes->redirect('/pages/redirect', ['controller' => 'Pages', 'action' => 'display', 'third_page'], ['status' => 302]);
    $routes->redirect('/pages/same-redirect', ['controller' => 'Pages', 'action' => 'display', 'third_page'], ['status' => 302]);
    $routes->connect('/pages/*', ['controller' => 'Pages', 'action' => 'display']);

    $routes->fallbacks(DashedRoute::class);
});
