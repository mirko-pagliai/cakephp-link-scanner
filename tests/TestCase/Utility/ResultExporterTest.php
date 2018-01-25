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

use Cake\TestSuite\TestCase;
use Cake\Utility\Xml;
use DOMDocument;
use LinkScanner\Utility\ResultExporter;

/**
 * ResultExporterTest class
 */
class ResultExporterTest extends TestCase
{
    /**
     * @var \LinkScanner\Utility\ResultExporter
     */
    protected $ResultExporter;

    /**
     * Example data
     * @var array
     */
    protected $example;

    /**
     * Setup the test case, backup the static object values so they can be
     * restored. Specifically backs up the contents of Configure and paths in
     *  App if they have not already been backed up
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->example = [
            'fullBaseUrl' => 'http://example.com/',
            'maxDepth' => 1,
            'startTime' => '2018-01-25 13:14:49',
            'elapsedTime' => 1,
            'checkedLinks' => 15,
            'links' => [
                [
                    'url' => 'http://example.com/',
                    'code' => 200,
                    'type' => 'text/html;charset=UTF-8'
                ],
                [
                    'url' => 'http://example.com/pageA.html',
                    'code' => 200,
                    'type' => 'text/html; charset=UTF-8',
                ],
                [
                    'url' => 'http://example.com/pageB.html',
                    'code' => 500,
                    'type' => 'text/html; charset=UTF-8',
                ],
                [
                    'url' => 'http://example.com/image.gif',
                    'code' => 200,
                    'type' => 'image/gif',
                ],
            ],
        ];

        $this->ResultExporter = new ResultExporter($this->example);
    }

    /**
     * Test for `asArray()` method
     * @test
     */
    public function testAsArray()
    {
        $filename = TMP . 'scan_as_array';
        $result = $this->ResultExporter->asArray($filename);
        $this->assertTrue($result);
        $this->assertFileExists($filename);

        $content = unserialize(file_get_contents($filename));
        $this->assertEquals($this->example, $content);
    }

    /**
     * Test for `asHtml()` method
     * @test
     */
    public function testAsHtml()
    {
        $filename = TMP . 'scan_as_html.html';
        $result = $this->ResultExporter->asHtml($filename);
        $this->assertTrue($result);
        $this->assertFileExists($filename);

        $dom = new DOMDocument;
        $dom->loadHTML(file_get_contents($filename));

        $content = [];

        foreach ($dom->getElementsByTagName('p') as $element) {
            list($name, $value) = (explode(': ', $element->nodeValue));
            $content[$name] = $value;
        }

        foreach ($dom->getElementsByTagName('tbody') as $element) {
            foreach ($element->getElementsByTagName('tr') as $element) {
                list($url, $code, $type) = array_filter(explode(PHP_EOL, $element->nodeValue));
                $content['links'][] = compact('url', 'code', 'type');
            }
        }

        $this->assertEquals($this->example, $content);
    }

    /**
     * Test for `asXml()` method
     * @test
     */
    public function testAsXml()
    {
        $filename = TMP . 'scan_as_array.xml';
        $result = $this->ResultExporter->asXml($filename);
        $this->assertTrue($result);
        $this->assertFileExists($filename);

        $content = Xml::toArray(Xml::build(file_get_contents($filename)))['root'];
        $content['links'] = $content['links']['link'];

        $this->assertEquals($this->example, $content);
    }
}
