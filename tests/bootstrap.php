<?php
/** @noinspection ALL */
declare(strict_types=1);

/**
 * This file is part of cakephp-link-scanner.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright   Copyright (c) Mirko Pagliai
 * @link        https://github.com/mirko-pagliai/cakephp-link-scanner
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Cache\Cache;
use Cake\Core\Configure;

ini_set('intl.default_locale', 'en_US');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

define('ROOT', dirname(__DIR__) . DS);
define('VENDOR', ROOT . 'vendor' . DS);
define('CORE_PATH', ROOT . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS);
define('CAKE', CORE_PATH . 'src' . DS);
define('TESTS', ROOT . 'tests' . DS);
define('TEST_APP', TESTS . 'test_app' . DS);
define('APP', TEST_APP . 'TestApp' . DS);
define('APP_DIR', 'TestApp');
define('WEBROOT_DIR', 'webroot');
define('WWW_ROOT', APP . 'webroot' . DS);
define('TMP', sys_get_temp_dir() . DS);
define('CACHE', TMP . 'cache' . DS);
define('CONFIG', APP . 'config' . DS);
define('LOGS', TMP . 'logs' . DS);
define('SESSIONS', TMP . 'sessions' . DS);

require dirname(__DIR__) . '/vendor/autoload.php';
require CORE_PATH . 'config' . DS . 'bootstrap.php';
require ROOT . 'config' . DS . 'bootstrap.php';

foreach (array_filter([LINK_SCANNER_TMP, LOGS, SESSIONS, CACHE . 'views', CACHE . 'models'], fn(string $dir): bool => !file_exists($dir)) as $dir) {
    mkdir($dir, 0777, true);
}

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'App',
    'encoding' => 'UTF-8',
    'base' => false,
    'baseUrl' => false,
    'dir' => APP_DIR,
    'webroot' => 'webroot',
    'wwwRoot' => WWW_ROOT,
    'fullBaseUrl' => 'http://localhost',
    'imageBaseUrl' => 'img/',
    'jsBaseUrl' => 'js/',
    'cssBaseUrl' => 'css/',
    'paths' => ['templates' => [APP . 'templates' . DS]],
]);
Configure::write('Session', ['defaults' => 'php']);
Configure::write('pluginsToLoad', ['LinkScanner']);
Cache::setConfig([
    '_cake_core_' => [
        'engine' => 'File',
        'prefix' => 'cake_core_',
        'serialize' => true,
    ],
]);

$_SERVER['PHP_SELF'] = '/';
