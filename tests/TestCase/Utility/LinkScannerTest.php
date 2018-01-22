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
        $this->LinkScanner->Client = $this->getMockBuilder(get_class($this->LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $this->LinkScanner->Client->method('get')
            ->will($this->returnCallback(function ($url) {
                $this->get($url);

                return $this->_response;
            }));
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

        $html = '<a href="page.html">Link</a>' . PHP_EOL .
            '<map name="example"><area href="area.htm"></map>' . PHP_EOL .
            '<audio src="file.mp3"></audio>' . PHP_EOL .
            '<embed src="helloworld.swf">' . PHP_EOL .
            '<frame src="frame1.html"></frame>' . PHP_EOL .
            '<iframe src="frame2.html"></iframe>' . PHP_EOL .
            '<img src="pic.jpg" />' . PHP_EOL .
            '<link rel="stylesheet" type="text/css" href="style.css">' . PHP_EOL .
            '<script type="text/javascript" href="script.js" />' . PHP_EOL .
            '<audio><source src="file2.mp3" type="audio/mpeg"></audio>' . PHP_EOL .
            '<video><track src="subtitles_en.vtt"></video>' . PHP_EOL .
            '<video src="movie.mp4"></video>';
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
     * Test for `get()` method
     * @test
     */
    public function testGet()
    {
        $result = $this->LinkScanner->get(['controller' => 'Pages', 'action' => 'display', 'nolinks']);
        $this->assertEquals(200, $result['code']);
        $this->assertNotEmpty($result['links']);
        $this->assertEquals('text/html', $result['type']);

        $result = $this->LinkScanner->get(['controller' => 'Pages', 'action' => 'display', 'home']);
        $this->assertEquals(200, $result['code']);
        $this->assertNotEmpty($result['links']);
        $this->assertEquals('text/html', $result['type']);

        $result = $this->LinkScanner->get(['controller' => 'Pages', 'action' => 'display', 'noexisting']);
        $this->assertEquals(500, $result['code']);
        $this->assertEmpty($result['links']);
        $this->assertEquals('text/html', $result['type']);
    }
}
