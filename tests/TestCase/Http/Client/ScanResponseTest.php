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
     * Test for `extractLinksFromBody()` method
     * @test
     */
    public function testExtractLinksFromBody()
    {
        $extractLinksFromBodyMethod = function ($body) {
            $response = $this->getResponseWithBody($body);
            $ScanResponse = new ScanResponse($response, Configure::read('App.fullBaseUrl'));

            return $this->invokeMethod($ScanResponse, 'extractLinksFromBody');
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
        $this->assertEquals($expected, $extractLinksFromBodyMethod($html));

        $html = '<html><body>' . $html . '</body></html>';
        $this->assertEquals($expected, $extractLinksFromBodyMethod($html));

        $html = '<b>No links here!</b>';
        $this->assertEquals([], $extractLinksFromBodyMethod($html));

        $html = '<a href="page.html">Link</a>' . PHP_EOL .
            '<a href="' . Configure::read('App.fullBaseUrl') . '/page.html">Link</a>';
        $expected = ['http://localhost/page.html'];
        $this->assertEquals($expected, $extractLinksFromBodyMethod($html));

        //Checks that the returned result is the same as that saved in the
        //  `extractedLinks` property as a cache
        $response = $this->getResponseWithBody($html);
        $ScanResponse = new ScanResponse($response, Configure::read('App.fullBaseUrl'));
        $result = $this->invokeMethod($ScanResponse, 'extractLinksFromBody');
        $expected = $this->getProperty($ScanResponse, 'extractedLinks');
        $this->assertEquals($expected, $result);

        //Changes the response body. The result remains unchanged, because the
        //  cached value will be returned
        $response = $this->getResponseWithBody('another body content...');
        $this->setProperty($ScanResponse, 'Response', $response);
        $result = $this->invokeMethod($ScanResponse, 'extractLinksFromBody');
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
}
