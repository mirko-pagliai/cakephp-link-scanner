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
namespace LinkScanner\Test\TestCase\Http\Client;

use Cake\Core\Configure;
use Cake\Http\Client\Response;
use Cake\TestSuite\Stub\Response as StubResponse;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\TestSuite\TestCase;
use Tools\BodyParser;

/**
 * ScanResponseTest class
 */
class ScanResponseTest extends TestCase
{
    /**
     * Test for `getContentType()` method
     * @test
     */
    public function testGetContentType()
    {
        $contentTypes = [
            'text/html; charset=UTF-8' => 'text/html',
            'application/rss+xml; charset=UTF-8' => 'application/rss+xml',
            'image/png' => 'image/png',
            'text/css' => 'text/css',
            'text/css; charset=UTF-8' => 'text/css',
            'application/javascript; charset=UTF-8' => 'application/javascript',
        ];

        foreach ($contentTypes as $originalContentType => $expectedContentType) {
            $Response = (new Response)->withHeader('content-type', $originalContentType);
            $ScanResponse = new ScanResponse($Response, Configure::read('App.fullBaseUrl'));
            $this->assertEquals($expectedContentType, $ScanResponse->getContentType());
        }
    }

    /**
     * Test for `isError()` method
     * @test
     */
    public function testIsError()
    {
        foreach ([new Response, new StubResponse] as $response) {
            $response = $response->withHeader('location', '/');

            $ScanResponse = new ScanResponse($response->withStatus(200), Configure::read('App.fullBaseUrl'));
            $this->assertFalse($ScanResponse->isError());

            $ScanResponse = new ScanResponse($response->withStatus(301), Configure::read('App.fullBaseUrl'));
            $this->assertFalse($ScanResponse->isError());

            $ScanResponse = new ScanResponse($response->withStatus(400), Configure::read('App.fullBaseUrl'));
            $this->assertTrue($ScanResponse->isError());
        }
    }

    /**
     * Test for `isRedirect()` method (through the `__call()` method)
     * @test
     */
    public function testIsRedirect()
    {
        foreach ([new Response, new StubResponse] as $response) {
            $response = $response->withHeader('location', '/');

            $ScanResponse = new ScanResponse($response->withStatus(200), Configure::read('App.fullBaseUrl'));
            $this->assertFalse($ScanResponse->isRedirect());

            $ScanResponse = new ScanResponse($response->withStatus(301), Configure::read('App.fullBaseUrl'));
            $this->assertTrue($ScanResponse->isRedirect());

            $ScanResponse = new ScanResponse($response->withStatus(400), Configure::read('App.fullBaseUrl'));
            $this->assertFalse($ScanResponse->isRedirect());
        }
    }

    /**
     * Test for `isOk()` method (through the `__call()` method)
     * @test
     */
    public function testIsOk()
    {
        foreach ([new Response, new StubResponse] as $response) {
            $response = $response->withHeader('location', '/');

            $ScanResponse = new ScanResponse($response->withStatus(200), Configure::read('App.fullBaseUrl'));
            $this->assertTrue($ScanResponse->isOk());

            $ScanResponse = new ScanResponse($response->withStatus(301), Configure::read('App.fullBaseUrl'));
            $this->assertFalse($ScanResponse->isOk());

            $ScanResponse = new ScanResponse($response->withStatus(400), Configure::read('App.fullBaseUrl'));
            $this->assertFalse($ScanResponse->isOk());
        }
    }

    /**
     * Test for `serialize()` and `unserialize()` methods
     * @test
     */
    public function testSerializeAndUnserialize()
    {
        //Creates a response (with body and status) and a scan response
        $response = new Response;
        $response = $response->withStatus(200);
        $response = $this->getResponseWithBody('a body with <a href="link1.html">Link</a>', $response);
        $ScanResponse = new ScanResponse($response, Configure::read('App.fullBaseUrl'));
        $ScanResponse = unserialize(serialize($ScanResponse));

        $this->assertInstanceof(ScanResponse::class, $ScanResponse);
        $this->assertEquals($this->getProperty($ScanResponse, 'fullBaseUrl'), Configure::read('App.fullBaseUrl'));
    }
}
