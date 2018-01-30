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
use Cake\Utility\Inflector;
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
                    'external' => false,
                    'type' => 'text/html;charset=UTF-8'
                ],
                [
                    'url' => 'http://example.com/pageA.html',
                    'code' => 200,
                    'external' => false,
                    'type' => 'text/html; charset=UTF-8',
                ],
                [
                    'url' => 'http://example.com/pageB.html',
                    'code' => 500,
                    'external' => false,
                    'type' => 'text/html; charset=UTF-8',
                ],
                [
                    'url' => 'http://example.com/image.gif',
                    'code' => 200,
                    'external' => false,
                    'type' => 'image/gif',
                ],
                [
                    'url' => 'http://google.com',
                    'code' => 200,
                    'external' => true,
                    'type' => 'text/html; charset=UTF-8',
                ],
            ],
        ];

        $this->ResultExporter = new ResultExporter($this->example);
    }

    /**
     * Teardown any static object changes and restore them
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        foreach (glob(TMP . "scan_as_*") as $file) {
            unlink($file);
        }
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
            $name = Inflector::variable($name);

            $content[$name] = $value;
        }

        foreach ($dom->getElementsByTagName('tbody') as $element) {
            foreach ($element->getElementsByTagName('tr') as $element) {
                list($url, $code, $external, $type) = array_map(function ($element) {
                    return $element->nodeValue;
                }, iterator_to_array($element->getElementsByTagName('td')));

                $external = $external === 'Yes';

                $content['links'][] = compact('url', 'code', 'external', 'type');
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

        foreach ($content['links'] as $k => $link) {
            $content['links'][$k]['external'] = (bool)$link['external'];
        }

        $this->assertEquals($this->example, $content);
    }
}
