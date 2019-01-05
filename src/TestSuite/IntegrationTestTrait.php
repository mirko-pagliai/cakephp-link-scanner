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
use Cake\Routing\Router;
use LinkScanner\ResultScan;
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
        $Client->method('get')->will($this->returnCallback(function () {
            $args = func_get_args();

            if (is_string($args[0])) {
                if (preg_match('/^https?:\/\/localhost\/?$/', $args[0])) {
                    $args[0] = ['controller' => 'Pages', 'action' => 'display', 'home'];
                } elseif (preg_match('/^http:\/\/localhost\/pages\/(.+)/', $args[0], $matches)) {
                    $args[0] = ['controller' => 'Pages', 'action' => 'display', $matches[1]];
                }
            }

            call_user_func_array([$this, 'get'], $args);

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
        $fullBaseUrl = $fullBaseUrl ?: Configure::readOrFail('App.fullBaseUrl');
        $fullBaseUrl = is_string($fullBaseUrl) ? $fullBaseUrl : Router::url($fullBaseUrl, true);

        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->setConstructorArgs([$fullBaseUrl])
            ->setMethods(['_createLockFile'])
            ->getMock();

        $LinkScanner->Client = $this->getClientReturnsFromTests();

        $LinkScanner->ResultScan = $this->getMockBuilder(ResultScan::class)
            ->setMethods(['getScannedUrl'])
            ->getMock();

        //This ensures the `getScannedUrl()` method returns all the urls as strings
        $LinkScanner->ResultScan->method('getScannedUrl')
            ->will($this->returnCallback(function () use ($LinkScanner) {
                return $LinkScanner->ResultScan->getIterator()->extract('url')->map(function ($url) {
                    return is_string($url) ? $url : Router::url($url, true);
                })->toArray();
            }));

        return $LinkScanner;
    }
}
