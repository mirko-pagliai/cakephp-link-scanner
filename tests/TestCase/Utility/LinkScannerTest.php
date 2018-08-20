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

use Cake\TestSuite\IntegrationTestCase;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ResultScan;
use LinkScanner\TestSuite\TestCaseTrait;
use LinkScanner\Utility\LinkScanner;

/**
 * LinkScannerTest class
 */
class LinkScannerTest extends IntegrationTestCase
{
    use TestCaseTrait;

    /**
     * @var \Cake\Event\EventManager
     */
    protected $EventManager;

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

        $this->LinkScanner = $this->getLinkScannerClientReturnsSampleResponse();
        $this->EventManager = $this->getEventManager();
    }

    /**
     * Teardown any static object changes and restore them
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        safe_unlink_recursive(TMP);
    }

    /**
     * Test for `getResponse()` method
     * @test
     */
    public function testGetResponse()
    {
        $getResponseMethod = function () {
            return $this->invokeMethod($this->LinkScanner, 'getResponse', func_get_args());
        };

        $this->LinkScanner = $this->getLinkScannerClientGetsFromTests();

        foreach ([
            'nolinks' => 200,
            'home' => 200,
            'noexisting' => 500,
        ] as $pageName => $expectedStatusCode) {
            $result = $getResponseMethod(['controller' => 'Pages', 'action' => 'display', $pageName]);
            $this->assertInstanceof(ScanResponse::class, $result);
            $this->assertEquals($expectedStatusCode, $result->getStatusCode());
            $this->assertStringStartsWith('text/html', $result->getContentType());
        }

        $this->LinkScanner = $this->getLinkScannerClientReturnsSampleResponse();
        $result = $getResponseMethod('http://www.google.it');
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringStartsWith('text/html', $result->getContentType());
    }

    /**
     * Test for `reset()` method
     * @test
     */
    public function testReset()
    {
        $this->LinkScanner->setMaxDepth(1)->scan();

        $exampleValues = [
            'currentDepth' => 1,
            'externalLinks' => ['value'],
            'endTime' => 2,
            'startTime' => 1,
        ];
        $expectedValuesAfterReset = [
            'currentDepth' => 0,
            'externalLinks' => [],
            'endTime' => 0,
            'startTime' => 0,
        ];

        foreach ($exampleValues as $name => $value) {
            $this->setProperty($this->LinkScanner, $name, $value);
        }

        $this->LinkScanner = $this->LinkScanner->reset();
        $this->assertInstanceof(LinkScanner::class, $this->LinkScanner);
        $this->assertInstanceof(ResultScan::class, $this->LinkScanner->ResultScan);
        $this->assertEmpty($this->LinkScanner->ResultScan->toArray());

        foreach ($expectedValuesAfterReset as $name => $expectedValue) {
            $this->assertEquals($expectedValue, $this->LinkScanner->$name);
        }
    }

    /**
     * Test for `export()` method
     * @test
     */
    public function testExport()
    {
        $this->LinkScanner->setMaxDepth(1)->scan();

        $result = $this->LinkScanner->export();
        $this->assertFileExists($result);
        $this->assertRegexp('/^' . preg_quote(TMP, '/') . 'results_google\.com_\d+$/', $result);
        $this->assertEventFired(LINK_SCANNER . '.resultsExported', $this->EventManager);

        $filename = TMP . 'results_as_array';
        $this->EventManager = $this->getEventManager();

        $result = $this->LinkScanner->export($filename);
        $this->assertFileExists($result);
        $this->assertEquals($filename, $result);
        $this->assertEventFired(LINK_SCANNER . '.resultsExported', $this->EventManager);
    }

    /**
     * Test for `export()` method, with a no existing file
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp /^Failed to export results to file `[\\\/\w\d:\-]+`$/
     * @test
     */
    public function testExportNoExistingFile()
    {
        $this->LinkScanner->setMaxDepth(1)->scan()->export(TMP . 'noExistingDir' . DS . 'result');
    }

    /**
     * Test for `export()` method, without the scan being performed
     * @expectedException RuntimeException
     * @expectedExceptionMessage There is no result to export. Perhaps the scan was not performed?
     * @test
     */
    public function testExportNoScan()
    {
        $this->LinkScanner->export();
    }

    /**
     * Test for `import()` method
     * @test
     */
    public function testImport()
    {
        $expectedLinkScanner = $this->LinkScanner;
        $expectedResultScan = $this->LinkScanner->ResultScan;

        $this->LinkScanner->setMaxDepth(1)->scan();
        $result = $this->LinkScanner->import($this->LinkScanner->export());
        $this->assertEquals($expectedLinkScanner, $result);
        $this->assertEquals($expectedResultScan, $result->ResultScan);
        $this->assertEventFired(LINK_SCANNER . '.resultsImported', $this->EventManager);
    }

    /**
     * Test for `import()` method, with a no existing file
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp /^Failed to import results from file `[\\\/\w\d:\-]+`$/
     * @test
     */
    public function testImportNoExistingFile()
    {
        $this->LinkScanner->import(TMP . 'noExistingDir' . DS . 'result');
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $result = $this->LinkScanner->setMaxDepth(1)->scan();

        $expectedEvents = ['scanStarted', 'scanCompleted', 'beforeScanUrl', 'afterScanUrl', 'foundLinksToBeScanned'];
        foreach ($expectedEvents as $eventName) {
            $this->assertEventFired(LINK_SCANNER . '.' . $eventName, $this->EventManager);
        }

        $this->assertInstanceof(LinkScanner::class, $result);
        $this->assertIsInt($this->LinkScanner->startTime);
        $this->assertIsInt($this->LinkScanner->endTime);
        $this->assertInstanceof(ResultScan::class, $this->LinkScanner->ResultScan);
        $this->assertCount(9, $this->LinkScanner->ResultScan);

        foreach ($this->LinkScanner->ResultScan as $item) {
            $this->assertFalse($item['external']);
            $this->assertContains($item['code'], [200, 500]);
            $this->assertContains($item['type'], ['text/html']);
        }

        $this->assertCount(13, $this->LinkScanner->externalLinks);

        $this->LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->setMethods(['_scan', 'reset'])
            ->getMock();

        $this->LinkScanner->expects($this->once())
             ->method('reset');

        $this->LinkScanner->expects($this->once())
             ->method('_scan');

        $this->LinkScanner->scan();
    }

    /**
     * Test for `scan()` method, with an invalid url
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp /^The lock file `[\\\/\w\d_:\-]+` already exists\. This means that a scan is already in progress\. If not, remove it manually$/
     * @test
     */
    public function testScanLockFileAlreadyExists()
    {
        file_put_contents(LINK_SCANNER_LOCK_FILE, null);

        $this->LinkScanner->scan();
    }

    /**
     * Test for `scan()` method, with no other links to scan
     * @test
     */
    public function testScanNoOtherLinks()
    {
        $params = ['controller' => 'Pages', 'action' => 'display', 'nolinks'];
        $LinkScanner = $this->getLinkScannerClientGetsFromTests($params);
        $EventManager = $this->getEventManager($LinkScanner);

        $LinkScanner->scan();

        $this->assertEventNotFired(LINK_SCANNER . '.foundLinksToBeScanned', $EventManager);
    }

    /**
     * Test for `scan()` method, with a response that doesn't contain html code
     * @test
     */
    public function testScanResponseNotHtml()
    {
        $params = ['controller' => 'Pages', 'action' => 'display', 'nohtml'];
        $LinkScanner = $this->getLinkScannerClientGetsFromTests($params);
        $EventManager = $this->getEventManager($LinkScanner);

        $LinkScanner->scan();

        $this->assertEventFired(LINK_SCANNER . '.' . 'responseNotHtml', $EventManager);
    }

    /**
     * Test for `scan()` method, with a response that is not ok
     * @test
     */
    public function testScanResponseNotOk()
    {
        $LinkScanner = $this->getLinkScannerClientGetsFromTests('http://localhost/noExisting');
        $EventManager = $this->getEventManager($LinkScanner);

        $LinkScanner->scan();

        $this->assertEventFired(LINK_SCANNER . '.' . 'responseNotOk', $EventManager);
    }

    /**
     * Test for `setFullBaseUrl()` method
     * @test
     */
    public function testSetFullBaseUrl()
    {
        foreach ([
            'http://fullBaseUrl.com',
            'http://fullBaseUrl.com/',
            'http://fullBaseUrl.com/site',
            'https://fullBaseUrl.com',
            'https://www.fullBaseUrl.com',
        ] as $fullBaseUrl) {
            $result = $this->LinkScanner->setFullBaseUrl($fullBaseUrl);
            $this->assertInstanceof(LinkScanner::class, $result);
            $this->assertEquals(rtrim($fullBaseUrl, '/'), $this->LinkScanner->fullBaseUrl);
            $this->assertEquals('fullBaseUrl.com', $this->LinkScanner->hostname);
        }
    }

    /**
     * Test for `setFullBaseUrl()` method, with an invalid string
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid url `invalid`
     * @test
     */
    public function testSetFullBaseUrlInvalidString()
    {
        $this->LinkScanner->setFullBaseUrl('invalid');
    }

    /**
     * Test for `setFullBaseUrl()` method, with an array
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid url `invalid`
     * @test
     */
    public function testSetFullBaseUrlArray()
    {
        $this->LinkScanner->setFullBaseUrl(['invalid']);
    }

    /**
     * Test for `setMaxDepth()` method
     * @test
     */
    public function testSetMaxDepth()
    {
        $this->assertEquals(0, $this->LinkScanner->maxDepth);

        foreach ([0, 1] as $depth) {
            $result = $this->LinkScanner->setMaxDepth($depth);
            $this->assertInstanceof(LinkScanner::class, $result);
            $this->assertEquals($depth, $this->LinkScanner->maxDepth);
        }
    }

    /**
     * Test for `setTimeout()` method
     * @test
     */
    public function testSetTimeout()
    {
        $this->assertEquals(30, $this->LinkScanner->timeout);

        foreach ([0, 1] as $timeout) {
            $result = $this->LinkScanner->setTimeout($timeout);
            $this->assertInstanceof(LinkScanner::class, $result);
            $this->assertEquals($timeout, $this->LinkScanner->timeout);
        }
    }
}
