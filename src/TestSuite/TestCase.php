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
use MeTools\TestSuite\TestCase as BaseTestCase;
use Zend\Diactoros\Stream;

/**
 * TestCase class
 */
abstract class TestCase extends BaseTestCase
{
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
     * Returns a stub of `Client`, where the `get()` method always returns a
     *  response with error (404 status code)
     * @return \Cake\Http\Client
     * @uses getResponseWithBody()
     */
    protected function getClientReturnsErrorResponse()
    {
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        //This allows the `Client` instance to use the `IntegrationTestCase::get()` method
        //It also analyzes the url of the test application and transforms them into parameter arrays
        $Client->method('get')->will($this->returnCallback(function () {
            return $this->getResponseWithBody(null)->withStatus(404);
        }));

        return $Client;
    }

    /**
     * Returns a stub of `Client`, where the `get()` method returns a sample
     *  response which is read from `examples/responses` files
     * @return \Cake\Http\Client
     * @uses getResponseWithBody()
     */
    protected function getClientReturnsSampleResponse()
    {
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        $Client->method('get')->will($this->returnCallback(function ($url) {
            $responseFile = TESTS . 'examples' . DS . 'responses' . DS . 'google_response';
            $bodyFile = TESTS . 'examples' . DS . 'responses' . DS . 'google_body';

            $response = is_readable($responseFile) ? safe_unserialize(file_get_contents($responseFile)) : (new Client(['redirect' => true]))->get($url);
            is_readable($responseFile) ? null : file_put_contents($responseFile, serialize($response));

            $body = is_readable($bodyFile) ? safe_unserialize(file_get_contents($bodyFile)) : (string)$response->getBody();
            is_readable($bodyFile) ? null : file_put_contents($bodyFile, serialize($body));

            return $this->getResponseWithBody($body, $response);
        }));

        return $Client;
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