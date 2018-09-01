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

/**
 * ScanResponseTest class
 */
class ScanResponseTest extends TestCase
{
    use TestCaseTrait;

    /**
     * Test for `bodyIsHtml()` method
     * @test
     */
    public function testBodyIsHtml()
    {
        foreach ([
            '<b>String</b>' => true,
            '</b>' => true,
            '<b>String' => true,
            '<tag>String</tag>' => true,
            'String' => false,
            '' => false,
        ] as $string => $expected) {
            $response = $this->getResponseWithBody($string);
            $ScanResponse = new ScanResponse($response, Configure::read('App.fullBaseUrl'));

            $this->assertEquals($expected, $ScanResponse->bodyIsHtml());
        }
    }

    /**
     * Test for `getContentType()` method
     * @test
     */
    public function testGetContentType()
    {
        $contentTypes = [
            'text/html; charset=UTF-8' => 'text/html',
        ];

        foreach ($contentTypes as $originalContentType => $expectedContentType) {
            $Response = (new Response)->withHeader('content-type', $originalContentType);
            $ScanResponse = new ScanResponse($Response, Configure::read('App.fullBaseUrl'));
            $this->assertEquals($expectedContentType, $ScanResponse->getContentType());
        }
    }

    /**
     * Test for `getExtractedLinks()` method
     * @test
     */
    public function testGetExtractedLinks()
    {
        $getExtractedLinksMethod = function ($body) {
            $ScanResponse = new ScanResponse($this->getResponseWithBody($body), Configure::read('App.fullBaseUrl'));

            return $ScanResponse->getExtractedLinks();
        };

        $html = '<a href="/page.html#fragment">Link</a>' . PHP_EOL .
            '<map name="example"><area href="area.htm"></map>' . PHP_EOL .
            '<audio src="/file.mp3"></audio>' . PHP_EOL .
            '<embed src="helloworld.swf">' . PHP_EOL .
            '<frame src="frame1.html"></frame>' . PHP_EOL .
            '<iframe src="frame2.html"></iframe>' . PHP_EOL .
            '<img src="pic.jpg" />' . PHP_EOL .
            '<link rel="stylesheet" type="text/css" href="style.css">' . PHP_EOL .
            '<script type="text/javascript" href="script.js" />' . PHP_EOL .
            '<audio><source src="file2.mp3" type="audio/mpeg"></audio>' . PHP_EOL .
            '<video><track src="subtitles_en.vtt"></video>' . PHP_EOL .
            '<video src="//localhost/movie.mp4"></video>';
        $expected = [
            'http://localhost/page.html',
            'http://localhost/area.htm',
            'http://localhost/file.mp3',
            'http://localhost/helloworld.swf',
            'http://localhost/frame1.html',
            'http://localhost/frame2.html',
            'http://localhost/pic.jpg',
            'http://localhost/style.css',
            'http://localhost/file2.mp3',
            'http://localhost/subtitles_en.vtt',
            'http://localhost/movie.mp4',
        ];
        $this->assertEquals($expected, $getExtractedLinksMethod($html));

        $html = '<html><body>' . $html . '</body></html>';
        $this->assertEquals($expected, $getExtractedLinksMethod($html));

        $html = '<b>No links here!</b>';
        $this->assertEquals([], $getExtractedLinksMethod($html));

        $html = '<a href="page.html">Link</a>' . PHP_EOL .
            '<a href="' . Configure::read('App.fullBaseUrl') . '/page.html">Link</a>';
        $expected = ['http://localhost/page.html'];
        $this->assertEquals($expected, $getExtractedLinksMethod($html));

        //Checks that the returned result is the same as that saved in the
        //  `extractedLinks` property as a cache
        $response = $this->getResponseWithBody($html);
        $ScanResponse = new ScanResponse($response, Configure::read('App.fullBaseUrl'));
        $result = $this->invokeMethod($ScanResponse, 'getExtractedLinks');
        $expected = $this->getProperty($ScanResponse, 'extractedLinks');
        $this->assertEquals($expected, $result);

        //Changes the response body. The result remains unchanged, because the
        //  cached value will be returned
        $response = $this->getResponseWithBody('another body content...');
        $this->setProperty($ScanResponse, 'Response', $response);
        $result = $this->invokeMethod($ScanResponse, 'getExtractedLinks');
        $this->assertEquals($expected, $result);
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
        //Creates a response. Sets body and status
        $response = new Response;
        $response = $response->withStatus(200);
        $response = $this->getResponseWithBody('a body', $response);

        //Creates a scan response. Sets extracted links
        $ScanResponse = new ScanResponse($response, Configure::read('App.fullBaseUrl'));
        $this->setProperty($ScanResponse, 'extractedLinks', ['link1', 'link2']);

        $unserialized = unserialize(serialize($ScanResponse));
        $this->assertInstanceof(ScanResponse::class, $unserialized);
        $this->assertEquals($this->getProperty($unserialized, 'extractedLinks'), ['link1', 'link2']);
        $this->assertEquals($this->getProperty($unserialized, 'fullBaseUrl'), Configure::read('App.fullBaseUrl'));
        $this->assertNotEmpty($this->getProperty($unserialized, 'tags'));

        $this->assertInstanceof(Response::class, $unserialized->Response);
        $this->assertTrue($unserialized->Response->isOk());
        $this->assertTextEquals('a body', $unserialized->Response->getBody());
    }
}
