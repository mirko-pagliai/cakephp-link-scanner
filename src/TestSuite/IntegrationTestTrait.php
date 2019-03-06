<?php
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
namespace LinkScanner\TestSuite;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\Routing\Router;
use LinkScanner\Utility\LinkScanner;
use MeTools\TestSuite\IntegrationTestTrait as BaseIntegrationTestTrait;

/**
 * A trait intended to make integration tests of your controllers easier
 */
trait IntegrationTestTrait
{
    use BaseIntegrationTestTrait;

    /**
     * Returns a stub of `Client`, where the `get()` method uses the
     *  `IntegrationTestTrait::get()` method and allows you to get responses from
     *  the test app
     * @return \Cake\Http\Client|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getClientReturnsFromTests()
    {
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        //This allows the `Client` instance to use the `IntegrationTestCase::get()` method
        //It also analyzes the url of the test application and transforms them into parameter arrays
        $Client->method('get')->will($this->returnCallback(function ($url) {
            if (is_string($url) && preg_match('/^http:\/\/localhost\/?(pages\/(.+))?$/', $url, $matches)) {
                $url = ['controller' => 'Pages', 'action' => 'display', empty($matches[2]) ? 'home' : $matches[2]];
            }

            call_user_func([$this, 'get'], $url);

            if (!$this->_response instanceof Response) {
                $response = new Response([], (string)$this->_response->getBody());
                foreach ($this->_response->getHeaders() as $name => $value) {
                    $response = $response->withHeader($name, $value);
                }

                $this->_response = $response->withStatus($this->_response->getStatusCode(), $this->_response->getReasonPhrase());
            }

            return $this->_response;
        }));

        return $Client;
    }

    /**
     * Returns a stub of `LinkScanner` instance, with the `Client::get()`
     *  method uses the `IntegrationTestTrait::get()` method and allows you to
     *  get responses from the test app
     * @param string|array|null $fullBaseUrl Full base url
     * @return \LinkScanner\Utility\LinkScanner|\PHPUnit_Framework_MockObject_MockObject
     * @uses getClientReturnsFromTests()
     */
    protected function getLinkScannerClientReturnsFromTests($fullBaseUrl = null)
    {
        $fullBaseUrl = $fullBaseUrl ?: Configure::read('App.fullBaseUrl', 'http://localhost');
        $fullBaseUrl = is_string($fullBaseUrl) ? $fullBaseUrl : Router::url($fullBaseUrl, true);

        return $this->getMockBuilder(LinkScanner::class)
            ->setConstructorArgs([$fullBaseUrl, $this->getClientReturnsFromTests()])
            ->setMethods(['_createLockFile'])
            ->getMock();
    }
}
