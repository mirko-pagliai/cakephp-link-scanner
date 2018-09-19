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
use Cake\TestSuite\TestCase;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\TestSuite\TestCaseTrait;
use Tools\BodyParser;

/**
 * ScanResponseTest class
 */
class ScanResponseTest extends TestCase
{
    use TestCaseTrait;

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
     * Test for `isOk()` method
     * @test
     */
    public function testIsOk()
    {
        foreach ([new Response, new StubResponse] as $response) {
            $response = $response->withStatus(200);
            $ScanResponse = new ScanResponse($response, Configure::read('App.fullBaseUrl'));
            $this->assertTrue($ScanResponse->isOk());

            $response = $response->withStatus(400);
            $ScanResponse = new ScanResponse($response, Configure::read('App.fullBaseUrl'));
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

        $this->assertInstanceof(BodyParser::class, $ScanResponse->BodyParser);
        $this->assertEquals(['http://localhost/link1.html'], $ScanResponse->BodyParser->extractLinks());
        $this->assertTrue($ScanResponse->BodyParser->isHtml());
    }
}
