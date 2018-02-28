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

use Cake\Http\Client\Response;
use LinkScanner\Utility\LinkScanner;
use Reflection\ReflectionTrait;
use Zend\Diactoros\Stream;

/**
 * This trait provided some methods that can be used in different tests
 */
trait TestCaseTrait
{
    use ReflectionTrait;

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
        $LinkScanner = new LinkScanner($fullBaseUrl);

        $LinkScanner->Client = $this->getMockBuilder(get_class($LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $LinkScanner->Client->method('get')->will($this->returnCallback(function ($url) {
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

        $LinkScanner->Client = $this->getMockBuilder(get_class($LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $LinkScanner->Client->method('get')->will($this->returnCallback(function () {
            $response = unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_response'));
            $body = unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_body'));

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