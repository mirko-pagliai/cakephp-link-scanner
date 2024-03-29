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
use Cake\TestSuite\TestCase as BaseTestCase;
use Laminas\Diactoros\Stream;
use LinkScanner\Utility\LinkScanner;

/**
 * TestCase class
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @var \LinkScanner\Utility\LinkScanner
     */
    protected LinkScanner $LinkScanner;

    /**
     * Called after every test method
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        if (file_exists(LINK_SCANNER_TMP . 'link_scanner_lock_file')) {
            unlink(LINK_SCANNER_TMP . 'link_scanner_lock_file');
        }

        Cache::clear('LinkScanner');
    }

    /**
     * Asserts that a global event was not fired. You must track events in your event manager for this assertion to work
     * @param string $name Event name
     * @param \Cake\Event\EventManager|null $eventManager Event manager to check, defaults to global event manager
     * @return void
     */
    public function assertEventNotFired(string $name, ?EventManager $eventManager = null): void
    {
        $eventList = ($eventManager ?: EventManager::instance())->getEventList() ?: new EventList();
        $eventHasFired = $eventList->hasEvent($name);

        $this->assertFalse($eventHasFired, sprintf('Failed asserting that \'%s\' was not fired.', $name));
    }

    /**
     * Returns a stub of `Client`
     * @param array $methods Methods to mock
     * @psalm-param list<non-empty-string> $methods
     * @return \Cake\Http\Client&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getClientStub(array $methods = ['get']): Client
    {
        /** @var \Cake\Http\Client&\PHPUnit\Framework\MockObject\MockObject $Client */
        $Client = $this->getMockBuilder(Client::class)->onlyMethods($methods)->getMock();

        return $Client;
    }

    /**
     * Returns a stub of `Client`, where the `get()` method always returns a response with error (404 status code)
     * @return \Cake\Http\Client&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getClientReturnsErrorResponse(): Client
    {
        //This allows the `Client` instance to use the `IntegrationTestCase::get()` method
        //It also analyzes the url of the test application and transforms them into parameter arrays
        $Client = $this->getClientStub();
        $Client->method('get')->willReturn($this->getResponseWithBody('')->withStatus(404));

        return $Client;
    }

    /**
     * Returns a stub of `Client`, where the `get()` method returns a redirect on the third request
     * @return \Cake\Http\Client|\PHPUnit\Framework\MockObject\MockObject
     * @since 1.1.5
     */
    protected function getClientReturnsRedirect(): object
    {
        $Client = $this->getClientStub();
        $Client->method('get')->willReturnCallback(function (string $url): Response {
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
        });

        return $Client;
    }

    /**
     * Returns a stub of `Client`, where the `get()` method returns a sample response which is read from
     *  `examples/responses` files
     * @return \Cake\Http\Client&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getClientReturnsSampleResponse(): Client
    {
        $Client = $this->getClientStub();
        $Client->method('get')->willReturnCallback(function (string $url): Response {
            //Ties to get the response from a cached file. If it doesn't exist, it will retrieve it via a GET request.
            $responseFile = TESTS . 'examples' . DS . 'responses' . DS . 'google_response';
            $getResponse = fn(): Response => is_readable($responseFile) ? unserialize(file_get_contents($responseFile) ?: '') : (new Client(['redirect' => true]))->get($url);
            $bodyFile = TESTS . 'examples' . DS . 'responses' . DS . 'google_body';
            $body = is_readable($bodyFile) ? unserialize(file_get_contents($bodyFile) ?: '') : (string)$getResponse()->getBody();
            if (!is_readable($responseFile) || !is_readable($bodyFile)) {
                file_put_contents($responseFile, serialize($getResponse()));
                file_put_contents($bodyFile, serialize($body));
            }

            return $this->getResponseWithBody($body, $getResponse());
        });

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
     * Gets a `Response` instance, writes a new body string and returns
     * @param string $body Body of the response
     * @param \Cake\Http\Client\Response|null $response A `Response` instance or `null` to create a new instance
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
