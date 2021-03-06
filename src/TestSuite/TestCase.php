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

use Cake\Cache\Cache;
use Cake\Event\EventList;
use Cake\Event\EventManager;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use LinkScanner\Utility\LinkScanner;
use MeTools\TestSuite\TestCase as BaseTestCase;
use Tools\TestSuite\BackwardCompatibilityTrait;
use Zend\Diactoros\Stream;

/**
 * TestCase class
 */
abstract class TestCase extends BaseTestCase
{
    use BackwardCompatibilityTrait;

    /**
     * @var \LinkScanner\Utility\LinkScanner
     */
    protected $LinkScanner;

    /**
     * Called after every test method
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        @unlink(TMP . 'cakephp-link-scanner' . DS . 'link_scanner_lock_file');

        Cache::clear('LinkScanner');
    }

    /**
     * Asserts that a global event was not fired. You must track events in your
     *  event manager for this assertion to work
     * @param string $name Event name
     * @param \Cake\Event\EventManager|null $eventManager Event manager to check,
     *  defaults to global event manager
     * @return void
     */
    public function assertEventNotFired(string $name, ?EventManager $eventManager = null): void
    {
        $eventManager = $eventManager ?: EventManager::instance();
        $eventHasFired = $eventManager->getEventList()->hasEvent($name);

        $this->assertFalse($eventHasFired, sprintf('Failed asserting that \'%s\' was not fired.', $name));
    }

    /**
     * Returns a stub of `Client`, where the `get()` method always returns a
     *  response with error (404 status code)
     * @return \Cake\Http\Client|\PHPUnit\Framework\MockObject\MockObject
     * @uses getResponseWithBody()
     */
    protected function getClientReturnsErrorResponse(): object
    {
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        //This allows the `Client` instance to use the `IntegrationTestCase::get()` method
        //It also analyzes the url of the test application and transforms them into parameter arrays
        $Client->method('get')->will($this->returnValue($this->getResponseWithBody('')->withStatus(404)));

        return $Client;
    }

    /**
     * Returns a stub of `Client`, where the `get()` method returns a redirect
     *  on the third request
     * @return \Cake\Http\Client|\PHPUnit\Framework\MockObject\MockObject
     * @since 1.1.5
     */
    protected function getClientReturnsRedirect(): object
    {
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        $Client->method('get')->will($this->returnCallback(function (string $url): Response {
            switch ($url) {
                case 'http://localhost/aPageWithRedirect':
                    $response = new Response(['Location:http://localhost/redirectTarget']);
                    $response = $response->withStatus(307);

                    break;
                case 'http://localhost/redirectTarget':
                    $response = new Response();
                    $response = $response->withStatus(200);

                    break;
                default:
                    $response = new Response([], '<a href=\'http://localhost/aPageWithRedirect\'>aPageWithRedirect</a>');
                    $response = $response->withStatus(200);

                    break;
            }

            return $response;
        }));

        return $Client;
    }

    /**
     * Returns a stub of `Client`, where the `get()` method returns a sample
     *  response which is read from `examples/responses` files
     * @return \Cake\Http\Client|\PHPUnit\Framework\MockObject\MockObject
     * @uses getResponseWithBody()
     */
    protected function getClientReturnsSampleResponse(): object
    {
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        $Client->method('get')->will($this->returnCallback(function (string $url): Response {
            $responseFile = TESTS . 'examples' . DS . 'responses' . DS . 'google_response';
            $bodyFile = TESTS . 'examples' . DS . 'responses' . DS . 'google_body';
            $getResponse = function () use ($url): Response {
                return (new Client(['redirect' => true]))->get($url);
            };

            if (is_readable($responseFile)) {
                $getResponse = function () use ($responseFile) {
                    return @unserialize(file_get_contents($responseFile) ?: '');
                };
            }
            is_readable($responseFile) ? null : file_put_contents($responseFile, serialize($getResponse()));

            $body = is_readable($bodyFile) ? @unserialize(file_get_contents($bodyFile) ?: '') : (string)$getResponse()->getBody();
            is_readable($bodyFile) ? null : file_put_contents($bodyFile, serialize($body));

            return $this->getResponseWithBody($body, $getResponse());
        }));

        return $Client;
    }

    /**
     * Sets the event list and returns the `EventManager` instance
     * @param \LinkScanner\Utility\LinkScanner|null $LinkScanner `LinkScanner` instance or `null`
     * @return \Cake\Event\EventManager
     */
    protected function getEventManager(?LinkScanner $LinkScanner = null): EventManager
    {
        $eventManager = ($LinkScanner ?? $this->LinkScanner)->getEventManager();

        return $eventManager->setEventList($eventManager->getEventList() ?? new EventList());
    }

    /**
     * Gets a `Response` instance, writes a new body string and returns.
     *
     * If `$response` is null, a new `Response` instance will be created.
     * @param string $body Body of the response
     * @param \Cake\Http\Client\Response|null $response A `Response` instance or
     *  `null` to create a new instance
     * @return \Cake\Http\Client\Response
     */
    protected function getResponseWithBody(string $body, ?Response $response = null): Response
    {
        $stream = new Stream('php://memory', 'wb+');
        $stream->write($body);
        $stream->rewind();

        return ($response ?: new Response())->withBody($stream);
    }
}
