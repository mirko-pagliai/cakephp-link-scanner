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

use Cake\Event\EventList;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\Routing\Router;
use LinkScanner\ResultScan;
use LinkScanner\Utility\LinkScanner;
use Tools\ReflectionTrait;
use Tools\TestSuite\TestCaseTrait as BaseTestCaseTrait;
use Zend\Diactoros\Stream;

/**
 * This trait provided some methods that can be used in different tests
 */
trait TestCaseTrait
{
    use BaseTestCaseTrait;
    use ReflectionTrait;

    /**
     * Asserts that a global event was not fired. You must track events in your
     *  event manager for this assertion to work
     * @param string $name Event name
     * @param Cake\Event\EventManager|null $eventManager Event manager to check,
     *  defaults to global event manager
     * @return void
     */
    public function assertEventNotFired($name, $eventManager = null)
    {
        $eventManager = $eventManager ?: EventManager::instance();
        $eventHasFired = $eventManager->getEventList()->hasEvent($name);

        $this->assertFalse($eventHasFired, sprintf('Failed asserting that \'%s\' was not fired.', $name));
    }

    /**
     * Sets the event list and returns the `EventManager` instance
     * @param LinkScanner|null $LinkScanner `LinkScanner` instance or `null`
     * @return \Cake\Event\EventManager
     */
    protected function getEventManager(LinkScanner $LinkScanner = null)
    {
        $LinkScanner = $LinkScanner ?: $this->LinkScanner;
        $eventManager = $LinkScanner->getEventManager();
        $eventManager->setEventList(new EventList);

        return $eventManager;
    }

    /**
     * Returns a stubbed `LinkScanner` instance, where the `Client::get()`
     *  method calls the `IntegrationTestCase::get()` method and allows you to
     *  get responses from the test app
     * @param string|array|null $fullBaseUrl Full base url. If `null`, the
     *  `App.fullBaseUrl` value will be used
     * @return \LinkScanner\Utility\LinkScanner
     */
    protected function getLinkScannerClientGetsFromTests($fullBaseUrl = null)
    {
        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->disableOriginalConstructor()
            ->setMethods(['createLockFile', 'getScannedLinks', 'isExternalLink', 'setFullBaseUrl', 'reset'])
            ->getMock();

        //This rewrites the instructions of the constructor
        $fullBaseUrl = clean_url(is_string($fullBaseUrl) ? $fullBaseUrl : Router::url($fullBaseUrl, true));
        $this->setProperty($LinkScanner, 'ResultScan', new ResultScan);
        $this->setProperty($LinkScanner, 'fullBaseUrl', $fullBaseUrl);

        //This ensures the `getScannedLinks()` method returns all the urls as strings
        $LinkScanner->method('getScannedLinks')
            ->will($this->returnCallback(function () use ($LinkScanner) {
                return $LinkScanner->ResultScan->extract('url')->map(function ($url) {
                    return is_string($url) ? $url : Router::url($url, true);
                })->toArray();
            }));

        //`isExternalLink()` method returns false for links that start with
        //  `http://localhost/pages/`
        $LinkScanner->method('isExternalLink')
            ->will($this->returnCallback(function ($link) {
                return get_hostname_from_url($link) !== 'localhost';
            }));

        $LinkScanner->Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        //This allows the `Client` instance to use the `IntegrationTestCase::get()` method
        //It also analyzes the url of the test application and transforms them into parameter arrays
        $LinkScanner->Client->method('get')->will($this->returnCallback(function () {
            $url = func_get_arg(0);

            if (is_string($url)) {
                if ($url === 'http://localhost') {
                    $url = ['controller' => 'Pages', 'action' => 'display', 'home'];
                } elseif (preg_match('/^http:\/\/localhost\/pages\/(.+)/', $url, $matches)) {
                    $url = ['controller' => 'Pages', 'action' => 'display', $matches[1]];
                }
            }

            $this->get($url);

            return $this->_response;
        }));

        return $LinkScanner;
    }

    /**
     * Returns a stubbed `LinkScanner` instance, where the `Client::get()`
     *  method always returns a sample response which is read from
     *  `response_examples/google_response` and `response_examples/google_body`
     *  files
     * @return \LinkScanner\Utility\LinkScanner
     * @uses getResponseWithBody()
     */
    protected function getLinkScannerClientReturnsSampleResponse()
    {
        $LinkScanner = new LinkScanner('http://google.com');

        $LinkScanner->Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        $LinkScanner->Client->method('get')->will($this->returnCallback(function () {
            $response = safe_unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_response'));
            $body = safe_unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_body'));

            return $this->getResponseWithBody($body, $response);
        }));

        return $LinkScanner;
    }

    /**
     * Gets a `Response` instance, writes a new body string and returns.
     *
     * If `$response` is null, a new `Response` instance will be created.
     * @param string $body Body of the response
     * @param Response|null $response A `Response` instance or `null` to create
     *  a new instance
     * @return \Cake\Http\Client\Response
     */
    protected function getResponseWithBody($body, Response $response = null)
    {
        $stream = new Stream('php://memory', 'rw');
        $stream->write($body);

        $response = $response ?: new Response;
        $this->setProperty($response, 'stream', $stream);

        return $response;
    }
}
