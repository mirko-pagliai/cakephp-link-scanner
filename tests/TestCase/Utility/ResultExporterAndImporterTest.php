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
use LinkScanner\Utility\ResultExporter;
use LinkScanner\Utility\ResultImporter;

/**
 * ResultExporterAndImporterTest class
 */
class ResultExporterAndImporterTest extends TestCase
{
    /**
     * @var \LinkScanner\Utility\ResultExporter
     */
    protected $ResultExporter;

    /**
     * @var \LinkScanner\Utility\ResultImporter
     */
    protected $ResultImporter;

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

        $this->ResultImporter = new ResultImporter;
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

        $result = $this->ResultImporter->asArray($filename);
        $this->assertEquals($this->example, $result);
    }

    /**
     * Test for import as array with data not array
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage Invalid data
     * @test
     */
    public function testAsArrayDataNotArray()
    {
        $filename = TMP . 'scan_as_array';
        file_put_contents($filename, serialize('string'));

        $this->ResultImporter->asArray($filename);
    }

    /**
     * Test for import as array with data as empty array
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage Invalid data
     * @test
     */
    public function testAsArrayEmptyArray()
    {
        $filename = TMP . 'scan_as_array';
        (new ResultExporter([]))->asArray($filename);

        $this->ResultImporter->asArray($filename);
    }

    /**
     * Test for import as array with data not serialized correctly
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage Invalid data
     * @test
     */
    public function testAsArrayDataNotSerializedCorrectly()
    {
        $filename = TMP . 'scan_as_array';
        file_put_contents($filename, 'string');

        $this->ResultImporter->asArray($filename);
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

        $result = $this->ResultImporter->asHtml($filename);
        $this->assertEquals($this->example, $result);
    }

    /**
     * Test for import as html with data empty table
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage Invalid data
     * @test
     */
    public function testAsHtmlDataEmptyTable()
    {
        $html = '<p><strong>Full base url:</strong> http://localhost/</p>
            <p><strong>Max depth:</strong> 1</p>
            <p><strong>Start time:</strong> 2018-01-31 13:18:04</p>
            <p><strong>Elapsed time:</strong> 4</p>
            <p><strong>Checked links:</strong> 15</p>
            <table>
            <tbody>
            </tbody>
            </table>';

        $filename = TMP . 'scan_as_html.html';
        file_put_contents($filename, $html);

        $this->ResultImporter->asHtml($filename);
    }

    /**
     * Test for import as html with data missing table
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage Invalid data
     * @test
     */
    public function testAsHtmlDataMissingTable()
    {
        $html = '<p><strong>Full base url:</strong> http://localhost/</p>
            <p><strong>Max depth:</strong> 1</p>
            <p><strong>Start time:</strong> 2018-01-31 13:18:04</p>
            <p><strong>Elapsed time:</strong> 4</p>
            <p><strong>Checked links:</strong> 15</p>';

        $filename = TMP . 'scan_as_html.html';
        file_put_contents($filename, $html);

        $this->ResultImporter->asHtml($filename);
    }

    /**
     * Test for import as html with data not html
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage Invalid data
     * @test
     */
    public function testAsHtmlDataNotHtml()
    {
        $filename = TMP . 'scan_as_array';
        file_put_contents($filename, 'string');

        $this->ResultImporter->asHtml($filename);
    }

    /**
     * Test for import as html with data not string
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage Invalid data
     * @test
     */
    public function testAsHtmlDataNotString()
    {
        $filename = TMP . 'scan_as_array';
        file_put_contents($filename, []);

        $this->ResultImporter->asHtml($filename);
    }

    /**
     * Test for export as html with invalid data
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage Invalid data
     * @test
     */
    public function testAsHtmlInvalidTable()
    {
        $this->example['links'] = array_map(function ($result) {
            unset($result['code']);

            return $result;
        }, $this->example['links']);

        (new ResultExporter($this->example))->asHtml(TMP . 'scan_as_html.html');
    }

    /**
     * Test for `asXml()` method
     * @test
     */
    public function testAsXml()
    {
        $filename = TMP . 'scan_as_xml.xml';
        $result = $this->ResultExporter->asXml($filename);
        $this->assertTrue($result);
        $this->assertFileExists($filename);

        $result = $this->ResultImporter->asXml($filename);
        $this->assertEquals($this->example, $result);
    }

    /**
     * Test for export with a no writable directory
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage File or directory `/noExistingDir` not writable
     * @test
     */
    public function testExportNoWritableDir()
    {
        $this->ResultExporter->asXml('/noExistingDir/scan_as_array');
    }

    /**
     * Test for import with a no writable file
     * @expectedException \Cake\Network\Exception\InternalErrorException
     * @expectedExceptionMessage File or directory `/noExistingDir/scan_as_array` not readable
     * @test
     */
    public function testImportNoReadableFile()
    {
        $this->ResultImporter->asXml('/noExistingDir/scan_as_array');
    }
}
