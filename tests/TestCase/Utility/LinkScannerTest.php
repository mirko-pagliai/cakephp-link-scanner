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
namespace LinkScanner\Test\TestCase\Utility;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestCase;
use Cake\Utility\Hash;
use LinkScanner\Utility\LinkScanner;
use Reflection\ReflectionTrait;
use Zend\Diactoros\Stream;

/**
 * LinkScannerTest class
 */
class LinkScannerTest extends IntegrationTestCase
{
    use ReflectionTrait;

    /**
     * @var \LinkScanner\Utility\LinkScanner
     */
    protected $LinkScanner;

    /**
     * Setup the test case, backup the static object values so they can be
     * restored. Specifically backs up the contents of Configure and paths in
     *  App if they have not already been backed up
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->LinkScanner = new LinkScanner;
    }

    /**
     * Teardown any static object changes and restore them
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        foreach (glob(TMP . "results*") as $file) {
            unlink($file);
        }
    }

    /**
     * Internal method to get a `LinkScanner` instance with a stub for the
     *  `Client` instance.
     * @return LinkScanner
     */
    protected function getLinkScannerWithStubClient()
    {
        $this->LinkScanner = new LinkScanner('http://google.com');

        $this->LinkScanner->Client = $this->getMockBuilder(get_class($this->LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $this->LinkScanner->Client->method('get')
            ->will($this->returnCallback(function () {
                $request = unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_response'));
                $body = unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_body'));
                $stream = new Stream('php://memory', 'rw');
                $stream->write($body);
                $this->setProperty($request, 'stream', $stream);

                return $request;
            }));

        return $this->LinkScanner;
    }

    /**
     * Test for `getLinksFromHtml()` method
     * @test
     */
    public function testgetLinksFromHtml()
    {
        $getLinksFromHtmlMethod = function () {
            return $this->invokeMethod($this->LinkScanner, 'getLinksFromHtml', func_get_args());
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
            '<video src="http://localhost/movie.mp4"></video>';
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
        $this->assertEquals($expected, $getLinksFromHtmlMethod($html));

        $html = '<html><body>' . $html . '</body></html>';
        $this->assertEquals($expected, $getLinksFromHtmlMethod($html));

        $html = '<b>No links here!</b>';
        $this->assertEquals([], $getLinksFromHtmlMethod($html));

        $html = '<a href="page.html">Link</a>' . PHP_EOL .
            '<a href="' . Configure::read('App.fullBaseUrl') . '/page.html">Link</a>';
        $expected = ['http://localhost/page.html'];
        $this->assertEquals($expected, $getLinksFromHtmlMethod($html));
    }

    /**
     * Test for `isExternalUrl()` method
     * @test
     */
    public function testIsExternalUrl()
    {
        $isExternalUrlMethod = function () {
            return $this->invokeMethod($this->LinkScanner, 'isExternalUrl', func_get_args());
        };

        foreach ([
            ['controller' => 'Pages', 'action' => 'display', 'nolinks'],
            '//localhost',
            'http://localhost',
            'https://localhost',
            'http://localhost/page',
            '//localhost/page',
            'https://localhost/page',
            'relative.html',
            '/relative.html',
        ] as $url) {
            $this->assertFalse($isExternalUrlMethod($url));
        }

        foreach ([
            'http://localhost.com',
            '//localhost.com',
            'http://subdomain.localhost',
            'http://www.google.com',
            'http://google.com',
            '//google.com',
        ] as $url) {
            $this->assertTrue($isExternalUrlMethod($url));
        }
    }

    /**
     * Test for `get()` method
     * @test
     */
    public function testGet()
    {
        $getMethod = function () {
            return $this->invokeMethod($this->LinkScanner, 'get', func_get_args());
        };

        $this->LinkScanner->Client = $this->getMockBuilder(get_class($this->LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $this->LinkScanner->Client->method('get')
            ->will($this->returnCallback(function ($url) {
                $this->get($url);

                return $this->_response;
            }));

        $result = $getMethod(['controller' => 'Pages', 'action' => 'display', 'nolinks']);
        $this->assertInstanceof('stdClass', $result);
        $this->assertEquals(200, $result->code);
        $this->assertFalse($result->external);
        $this->assertNotEmpty($result->links);
        $this->assertStringStartsWith('text/html', $result->type);

        $result = $getMethod(['controller' => 'Pages', 'action' => 'display', 'home']);
        $this->assertEquals(200, $result->code);
        $this->assertFalse($result->external);
        $this->assertNotEmpty($result->links);
        $this->assertStringStartsWith('text/html', $result->type);

        $result = $getMethod(['controller' => 'Pages', 'action' => 'display', 'noexisting']);
        $this->assertEquals(500, $result->code);
        $this->assertFalse($result->external);
        $this->assertEmpty($result->links);
        $this->assertStringStartsWith('text/html', $result->type);

        $this->LinkScanner = $this->getLinkScannerWithStubClient();

        $result = $getMethod('http://www.google.it');
        $this->assertInstanceof('stdClass', $result);
        $this->assertEquals(200, $result->code);
        $this->assertTrue($result->external);
        $this->assertTrue(is_array($result->links));
        $this->assertStringStartsWith('text/html', $result->type);
    }

    /**
     * Test for `reset()` method
     * @test
     */
    public function testReset()
    {
        foreach (['alreadyScanned', 'externalLinks', 'resultMap'] as $property) {
            $this->setProperty($this->LinkScanner, $property, ['value']);
        }

        foreach (['currentDepth', 'elapsedTime', 'startTime'] as $property) {
            $this->setProperty($this->LinkScanner, $property, 1);
        }

        $result = $this->LinkScanner->reset();
        $this->assertInstanceof(get_class($this->LinkScanner), $result);

        foreach (['alreadyScanned', 'externalLinks', 'resultMap'] as $property) {
            $this->assertEquals([], $this->getProperty($this->LinkScanner, $property));
        }

        foreach (['currentDepth', 'elapsedTime', 'startTime'] as $property) {
            $this->assertEquals(0, $this->getProperty($this->LinkScanner, $property));
        }
    }

    /**
     * Test for `export()` method
     * @test
     */
    public function testExport()
    {
        $this->LinkScanner = $this->getLinkScannerWithStubClient();

        $this->LinkScanner->setMaxDepth(1)->scan();

        foreach (['array', 'html', 'xml'] as $format) {
            $result = $this->LinkScanner->export($format);

            $this->assertFileExists($result);
            $this->assertEquals(TMP, dirname($result) . DS);
            $this->assertRegexp(
                '/^results_google\.com_[\d\-]{10}\s[\d:]{8}(\.(html|xml))?$/',
                basename($result)
            );
        }

        //Tries with a custom filename
        $filename = TMP . 'results_custom_filename';
        $result = $this->LinkScanner->export('array', $filename);
        $this->assertFileExists($result);
        $this->assertEquals($result, $filename);
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $this->LinkScanner = $this->getLinkScannerWithStubClient();

        $result = $this->LinkScanner->setMaxDepth(1)->scan();

        $this->assertInstanceof('LinkScanner\Utility\LinkScanner', $result);

        $this->assertNotEmpty($this->getProperty($this->LinkScanner, 'startTime'));
        $this->assertTrue(is_int($this->getProperty($this->LinkScanner, 'elapsedTime')));

        $resultMap = $this->getProperty($this->LinkScanner, 'resultMap');
        $this->assertNotEmpty($resultMap);

        foreach ($resultMap as $item) {
            $this->assertEquals(['url', 'code', 'external', 'type'], array_keys($item));
            $this->assertFalse($item['external']);
            $this->assertContains($item['code'], [200, 500]);
            $this->assertContains($item['type'], ['text/html; charset=ISO-8859-1']);
        }

        $alreadyScanned = $this->getProperty($this->LinkScanner, 'alreadyScanned');
        $this->assertEquals($alreadyScanned, Hash::extract($resultMap, '{n}.url'));

        $this->assertCount(13, $this->getProperty($this->LinkScanner, 'externalLinks'));

        $this->LinkScanner = $this->getMockBuilder(get_class($this->LinkScanner))
            ->setMethods(['_scan', 'reset'])
            ->getMock();

        $this->LinkScanner->expects($this->once())
             ->method('reset');

        $this->LinkScanner->expects($this->once())
             ->method('_scan');

        $this->LinkScanner->scan();
    }

    /**
     * Test for `setMaxDepth()` method
     * @test
     */
    public function testSetMaxDepth()
    {
        $maxDepthProperty = function () {
            return $this->getProperty($this->LinkScanner, 'maxDepth');
        };

        $this->assertEquals(0, $maxDepthProperty());

        foreach ([0, 1] as $depth) {
            $result = $this->LinkScanner->setMaxDepth($depth);
            $this->assertInstanceof(get_class($this->LinkScanner), $result);
            $this->assertEquals($depth, $maxDepthProperty());
        }
    }
}
