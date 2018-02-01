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
use Cake\Http\Client\Response;
use Cake\TestSuite\IntegrationTestCase;
use Cake\Utility\Hash;
use LinkScanner\Utility\LinkScanner;
use Reflection\ReflectionTrait;

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
     * Test for `isHtmlString()` method
     * @test
     */
    public function testIsHtmlString()
    {
        $isHtmlStringMethod = function () {
            return $this->invokeMethod($this->LinkScanner, 'isHtmlString', func_get_args());
        };

        foreach ([
            '<b>String</b>',
            '</b>',
            '<b>String',
            '<tag>String</tag>',
        ] as $string) {
            $this->assertTrue($isHtmlStringMethod($string));
        }

        foreach ([
            'String',
            '',
        ] as $string) {
            $this->assertFalse($isHtmlStringMethod($string));
        }
    }

    /**
     * Test for `get()` method
     * @test
     */
    public function testGet()
    {
        $result = $this->LinkScanner->get('http://www.google.it');
        $this->assertInstanceof('stdClass', $result);
        $this->assertInstanceof('Cake\Http\Client\Response', $result->_response);
        $this->assertEquals(200, $result->code);
        $this->assertTrue($result->external);
        $this->assertNotEmpty($result->links);
        $this->assertStringStartsWith('text/html', $result->type);

        $this->LinkScanner = $this->getMockBuilder(get_class($this->LinkScanner))
            ->setMethods(['responseIsOk'])
            ->getMock();

        $this->LinkScanner->method('responseIsOk')
            ->will($this->returnCallback(function ($response) {
                return (new Response)->withStatus($response->getStatusCode())->isOk();
            }));

        $this->LinkScanner->Client = $this->getMockBuilder(get_class($this->LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $this->LinkScanner->Client->method('get')
            ->will($this->returnCallback(function ($url) {
                $this->get($url);

                return $this->_response;
            }));

        $result = $this->LinkScanner->get(['controller' => 'Pages', 'action' => 'display', 'nolinks']);
        $this->assertInstanceof('stdClass', $result);
        $this->assertInstanceof('Cake\TestSuite\Stub\Response', $result->_response);
        $this->assertEquals(200, $result->code);
        $this->assertFalse($result->external);
        $this->assertNotEmpty($result->links);
        $this->assertStringStartsWith('text/html', $result->type);

        $result = $this->LinkScanner->get(['controller' => 'Pages', 'action' => 'display', 'home']);
        $this->assertEquals(200, $result->code);
        $this->assertFalse($result->external);
        $this->assertNotEmpty($result->links);
        $this->assertStringStartsWith('text/html', $result->type);

        $result = $this->LinkScanner->get(['controller' => 'Pages', 'action' => 'display', 'noexisting']);
        $this->assertEquals(500, $result->code);
        $this->assertFalse($result->external);
        $this->assertEmpty($result->links);
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
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $this->LinkScanner = new LinkScanner('http://localhost');
        $result = $this->LinkScanner->setMaxDepth(1)->scan();

        $this->assertInstanceof('LinkScanner\Utility\LinkScanner', $result);

        $this->assertNotEmpty($this->getProperty($this->LinkScanner, 'startTime'));
        $this->assertNotEmpty($this->getProperty($this->LinkScanner, 'elapsedTime'));

        $resultMap = $this->getProperty($this->LinkScanner, 'resultMap');
        $this->assertNotEmpty($resultMap);

        foreach ($resultMap as $item) {
            $this->assertEquals(['url', 'external', 'code', 'type'], array_keys($item));
            $this->assertFalse($item['external']);
            $this->assertContains($item['code'], [200, 500]);
            $this->assertContains($item['type'], [
                'image/gif',
                'text/html; charset=UTF-8',
                'text/html;charset=UTF-8',
            ]);
        }

        $alreadyScanned = $this->getProperty($this->LinkScanner, 'alreadyScanned');
        $this->assertEquals($alreadyScanned, Hash::extract($resultMap, '{n}.url'));

        $this->assertEmpty($this->getProperty($this->LinkScanner, 'externalLinks'));

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

        $result = $this->LinkScanner->setMaxDepth(1);
        $this->assertInstanceof(get_class($this->LinkScanner), $result);
        $this->assertEquals(1, $maxDepthProperty());

        $result = $this->LinkScanner->setMaxDepth(0);
        $this->assertInstanceof(get_class($this->LinkScanner), $result);
        $this->assertEquals(0, $maxDepthProperty());
    }
}
