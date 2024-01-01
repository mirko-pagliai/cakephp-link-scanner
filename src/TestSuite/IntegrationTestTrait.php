<?php
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
namespace LinkScanner\TestSuite;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestTrait as BaseIntegrationTestTrait;
use LinkScanner\Utility\LinkScanner;

/**
 * A trait intended to make integration tests of your controllers easier
 * @property \Cake\Http\Response|\Cake\Http\Client\Response $_response
 */
trait IntegrationTestTrait
{
    use BaseIntegrationTestTrait;

    /**
     * Returns a stub of `Client`, where the `get()` method uses the `IntegrationTestTrait::get()` method and allows you
     *  to get responses from the test app
     * @return \Cake\Http\Client&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getClientReturnsFromTests(): Client
    {
        //This allows the `Client` instance to use the `IntegrationTestCase::get()` method
        //It also analyzes the url of the test application and transforms them into parameter arrays
        $Client = $this->getClientStub();
        $Client->method('get')->willReturnCallback(function ($url): Response {
            //Turns a url into an array of parameters, if possible
            if (is_string($url) && preg_match('/^http:\/\/localhost\/?(pages\/(.+))?$/', $url, $matches)) {
                $url = ['controller' => 'Pages', 'action' => 'display', $matches[2] ?? 'home'];
            }
            $this->get($url);

            //Makes sure to return a `Response` instance
            if (!$this->_response instanceof Response) {
                $Response = new Response([], (string)$this->_response->getBody());
                foreach ($this->_response->getHeaders() as $name => $value) {
                    $Response = $Response->withHeader($name, $value);
                }

                $this->_response = $Response->withStatus($this->_response->getStatusCode(), $this->_response->getReasonPhrase());
            }

            return $this->_response;
        });

        return $Client;
    }

    /**
     * Returns a stub of `LinkScanner` instance, with the `Client::get()` method uses the `IntegrationTestTrait::get()`
     *  method and allows you to get responses from the test app
     * @param string|array|null $fullBaseUrl Full base url
     * @return \LinkScanner\Utility\LinkScanner&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getLinkScannerClientReturnsFromTests($fullBaseUrl = null): LinkScanner
    {
        $fullBaseUrl = $fullBaseUrl ?: Configure::read('App.fullBaseUrl', 'http://localhost');
        $fullBaseUrl = is_string($fullBaseUrl) ? $fullBaseUrl : Router::url($fullBaseUrl, true);

        /** @var \LinkScanner\Utility\LinkScanner&\PHPUnit\Framework\MockObject\MockObject $LinkScanner */
        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->setConstructorArgs([$this->getClientReturnsFromTests()])
            ->onlyMethods(['_createLockFile'])
            ->getMock();

        return $LinkScanner->setConfig('fullBaseUrl', $fullBaseUrl);
    }
}
