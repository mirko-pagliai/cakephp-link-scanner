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

        if (!$eventManager->getEventList()) {
            $eventManager->setEventList(new EventList);
        }

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
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        //This allows the `Client` instance to use the `IntegrationTestCase::get()` method
        //It also analyzes the url of the test application and transforms them into parameter arrays
        $Client->method('get')->will($this->returnCallback(function () {
            $args = func_get_args();

            if (is_string($args[0])) {
                if ($args[0] === 'http://localhost') {
                    $args[0] = ['controller' => 'Pages', 'action' => 'display', 'home'];
                } elseif (preg_match('/^http:\/\/localhost\/pages\/(.+)/', $args[0], $matches)) {
                    $args[0] = ['controller' => 'Pages', 'action' => 'display', $matches[1]];
                }
            }

            call_user_func_array([$this, 'get'], $args);

            return $this->_response;
        }));

        $ResultScan = $this->getMockBuilder(ResultScan::class)
            ->setMethods(['getScannedUrl'])
            ->getMock();

        //This ensures the `getScannedUrl()` method returns all the urls as strings
        $ResultScan->method('getScannedUrl')->will($this->returnCallback(function () use ($ResultScan) {
            return $ResultScan->getIterator()->extract('url')->map(function ($url) {
                return is_string($url) ? $url : Router::url($url, true);
            })->toArray();
        }));

        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->disableOriginalConstructor()
            ->setMethods(['createLockFile'])
            ->getMock();

        //This rewrites constructor's instructions
        $fullBaseUrl = clean_url(is_string($fullBaseUrl) ? $fullBaseUrl : Router::url($fullBaseUrl, true), true);
        $this->setProperty($LinkScanner, 'Client', $Client);
        $this->setProperty($LinkScanner, 'ResultScan', $ResultScan);
        $this->setProperty($LinkScanner, 'fullBaseUrl', $fullBaseUrl);
        $this->setProperty($LinkScanner, 'hostname', 'localhost');

        return $LinkScanner;
    }

    /**
     * Returns a stubbed `LinkScanner` instance, where the `Client::get()`
     *  method always returns a sample response which is read from
     *  `examples/responses/google_response` and `examples/responses/google_body`
     *  files
     * @return \LinkScanner\Utility\LinkScanner
     * @uses getResponseWithBody()
     */
    protected function getLinkScannerClientReturnsSampleResponse()
    {
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        $Client->method('get')->will($this->returnCallback(function ($url) {
            $responseFile = TESTS . 'examples' . DS . 'responses' . DS . 'google_response';
            $bodyFile = TESTS . 'examples' . DS . 'responses' . DS . 'google_body';

            if (is_readable($responseFile)) {
                $response = safe_unserialize(file_get_contents($responseFile));
            } else {
                $response = (new Client(['redirect' => true]))->get($url);
                file_put_contents($responseFile, serialize($response));
            }

            if (is_readable($bodyFile)) {
                $body = safe_unserialize(file_get_contents($bodyFile));
            } else {
                $body = (string)$response->getBody();
                file_put_contents($bodyFile, serialize($body));
            }

            return $this->getResponseWithBody($body, $response);
        }));

        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->setConstructorArgs(['http://google.com'])
            ->setMethods(['createLockFile'])
            ->getMock();

        $this->setProperty($LinkScanner, 'Client', $Client);

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
        $response = $response ?: new Response;

        $stream = new Stream('php://memory', 'wb+');
        $stream->write($body);
        $stream->rewind();

        return $response->withBody($stream);
    }
}
