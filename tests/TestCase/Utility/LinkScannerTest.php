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

use Cake\Cache\Cache;
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
     * Can cointain some debug notices
     * @var array
     */
    protected $debug;

    /**
     * Setup the test case, backup the static object values so they can be
     * restored. Specifically backs up the contents of Configure and paths in
     *  App if they have not already been backed up
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        Cache::clearAll();
        Cache::disable();

        $this->LinkScanner = $this->getLinkScannerClientReturnsSampleResponse();
        $this->EventManager = $this->getEventManager();
        $this->debug = [];
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
        Cache::enable();

        $getResponseMethod = function () {
            return $this->invokeMethod($this->LinkScanner, 'getResponse', func_get_args());
        };
        $getResponseFromCache = function ($url) {
            return Cache::read(sprintf('response_%s', md5(serialize($url))), LINK_SCANNER);
        };

        $this->LinkScanner = $this->getLinkScannerClientGetsFromTests();

        foreach ([
            'nolinks' => 200,
            'home' => 200,
            'noexisting' => 500,
        ] as $pageName => $expectedStatusCode) {
            $params = ['controller' => 'Pages', 'action' => 'display', $pageName];

            $response = $getResponseMethod($params);
            $this->assertInstanceof(ScanResponse::class, $response);
            $this->assertEquals($expectedStatusCode, $response->getStatusCode());
            $this->assertStringStartsWith('text/html', $response->getContentType());

            $responseFromCache = $getResponseFromCache($params);
            $this->assertNotEmpty($responseFromCache);
            $this->assertInstanceof(ScanResponse::class, $responseFromCache);
        }

        $this->LinkScanner = $this->getLinkScannerClientReturnsSampleResponse();
        $response = $getResponseMethod('http://www.google.it');
        $this->assertInstanceof(ScanResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getContentType());

        $responseFromCache = $getResponseFromCache('http://www.google.it');
        $this->assertNotEmpty($responseFromCache);
        $this->assertInstanceof(ScanResponse::class, $responseFromCache);
    }

    /**
     * Test for `export()` method
     * @test
     */
    public function testExport()
    {
        $this->LinkScanner->setConfig('maxDepth', 1)->scan();

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
     * @expectedExceptionMessageRegExp /^Failed to export results to file `[\\\/\w\d:\-]+` with message `failed to open stream: No such file or directory`$/
     * @test
     */
    public function testExportNoExistingFile()
    {
        $this->LinkScanner->setConfig('maxDepth', 1)
            ->scan()
            ->export(TMP . 'noExistingDir' . DS . 'result');
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

        $this->LinkScanner->setConfig('maxDepth', 1)->scan();
        $result = $this->LinkScanner->import($this->LinkScanner->export());
        $this->assertEquals($expectedLinkScanner, $result);
        $this->assertEquals($expectedResultScan, $result->ResultScan);
        $this->assertEventFired(LINK_SCANNER . '.resultsImported', $this->EventManager);
    }

    /**
     * Test for `import()` method, with a no existing file
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp /^Failed to import results from file `[\\\/\w\d:\-]+` with message `failed to open stream: No such file or directory`$/
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
        $result = $this->LinkScanner->setConfig('maxDepth', 2)->scan();

        $expectedEvents = ['scanStarted', 'scanCompleted', 'beforeScanUrl', 'afterScanUrl', 'foundLinkToBeScanned'];
        foreach ($expectedEvents as $eventName) {
            $this->assertEventFired(LINK_SCANNER . '.' . $eventName, $this->EventManager);
        }

        $this->assertInstanceof(LinkScanner::class, $result);
        $this->assertIsInt($this->LinkScanner->startTime);
        $this->assertIsInt($this->LinkScanner->endTime);
        $this->assertInstanceof(ResultScan::class, $this->LinkScanner->ResultScan);
        $this->assertGreaterThan(1, $this->LinkScanner->ResultScan->count());

        foreach ($this->LinkScanner->ResultScan as $item) {
            $this->assertRegExp('/^http:\/\/google\.com/', $item->url);
            $this->assertFalse($item->external);
            $this->assertContains($item->code, [200, 500]);
            $this->assertEquals($item->type, 'text/html');
        }

        $this->assertGreaterThan(1, $this->LinkScanner->externalLinks);

        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->setMethods(['_scan'])
            ->getMock();

        $LinkScanner->expects($this->once())
             ->method('_scan');

        $LinkScanner->scan();
    }

    /**
     * Test for `scan()` method, from tests
     * @test
     */
    public function testScanFromTests()
    {
        $this->debug = [];

        $this->getEventManager()->instance()
            ->on(LINK_SCANNER . '.beforeScanUrl', function ($event, $url) {
                $this->debug[] = sprintf('Scanning %s', $url);
            })
            ->on(LINK_SCANNER . '.foundLinkToBeScanned', function ($event, $link) {
                $this->debug[] = sprintf('Found link: %s', $link);
            });

        $expectedResults = [
            'http://localhost',
            'http://localhost/pages/first_page',
            'http://localhost/favicon.ico',
            'http://localhost/css/default.css',
            'http://localhost/js/default.js',
            'http://localhost/pages/second_page',

        ];
        $params = ['controller' => 'Pages', 'action' => 'display', 'home'];
        $LinkScanner = $this->getLinkScannerClientGetsFromTests($params);
        $LinkScanner->scan();
        $this->assertCount(6, $LinkScanner->ResultScan);
        $results = $LinkScanner->ResultScan->extract('url')->toArray();
        $this->assertEquals($expectedResults, $results);
        $this->assertNotEmpty($this->debug);

        $LinkScanner = $this->getLinkScannerClientGetsFromTests($params);
        $LinkScanner->setConfig('maxDepth', 1)->scan();
        $this->assertCount(1, $LinkScanner->ResultScan);
        $item = $LinkScanner->ResultScan->first();
        $this->assertEquals($item->code, 200);
        $this->assertFalse($item->external);
        $this->assertNull($item->referer);
        $this->assertEquals($item->type, 'text/html');
        $this->assertEquals($item->url, 'http://localhost');
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

        (new LinkScanner)->scan();
    }

    /**
     * Test for `scan()` method, with a no existing url
     * @test
     */
    public function testScanNoExistingUrl()
    {
        $LinkScanner = new LinkScanner('http://noExisting');
        $LinkScanner->getClient()->setConfig('timeout', 1);
        $LinkScanner->scan();
        $this->assertTrue($LinkScanner->ResultScan->isEmpty());
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

        $this->assertEventNotFired(LINK_SCANNER . '.foundLinkToBeScanned', $EventManager);
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
            'http://www.fullBaseUrl.com/site',
            'https://fullBaseUrl.com',
            'https://www.fullBaseUrl.com',
            'ftp://fullBaseUrl.com',
            'ftp://www.fullBaseUrl.com',
        ] as $fullBaseUrl) {
            $result = $this->LinkScanner->setFullBaseUrl($fullBaseUrl);
            $this->assertInstanceof(LinkScanner::class, $result);
            $this->assertRegexp('/^(ftp|https?):\/\/fullBaseUrl\.com(\/(site)?)?$/', $this->LinkScanner->fullBaseUrl);
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
}
