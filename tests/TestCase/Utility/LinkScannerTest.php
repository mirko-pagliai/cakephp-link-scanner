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
     * @var string
     */
    protected $fullBaseUrl;

    /**
     * Setup the test case, backup the static object values so they can be
     * restored. Specifically backs up the contents of Configure and paths in
     *  App if they have not already been backed up
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        safe_unlink(LINK_SCANNER);

        $this->debug = [];
        $this->fullBaseUrl = 'http://google.com';
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl, null, $this->getClientReturnsSampleResponse());
        $this->EventManager = $this->getEventManager();
    }

    /**
     * Teardown any static object changes and restore them
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        Cache::clearAll();
        Cache::disable();

        safe_unlink_recursive(TMP);
    }

    /**
     * Test for `_getResponse()` method
     * @test
     */
    public function testGetResponse()
    {
        Cache::enable();

        $getResponseMethod = function ($url) {
            return $this->invokeMethod($this->LinkScanner, '_getResponse', [$url]);
        };
        $getResponseFromCache = function ($url) {
            return Cache::read(sprintf('response_%s', md5(serialize($url))), LINK_SCANNER);
        };

        $response = $getResponseMethod($this->fullBaseUrl);
        $this->assertInstanceof(ScanResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getContentType());

        $responseFromCache = $getResponseFromCache($this->fullBaseUrl);
        $this->assertNotEmpty($responseFromCache);
        $this->assertInstanceof(ScanResponse::class, $responseFromCache);

        $this->LinkScanner = $this->getLinkScannerClientReturnsFromTests();

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
            if ($response->isOk()) {
                $this->assertNotEmpty($responseFromCache);
                $this->assertInstanceof(ScanResponse::class, $responseFromCache);
            } else {
                $this->assertEmpty($responseFromCache);
            }
        }
    }

    /**
     * Test for `export()` method
     * @test
     */
    public function testExport()
    {
        $this->LinkScanner->setConfig('maxDepth', 1)->scan();

        //Filename can be `null`, relative or absolute
        foreach ([
            null => LINK_SCANNER_TARGET . DS . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime,
            'example' => LINK_SCANNER_TARGET . DS . 'example',
            TMP . 'example' => TMP . 'example',
        ] as $filenameWhereToExport => $expectedFilename) {
            $result = $this->LinkScanner->export($filenameWhereToExport);
            $this->assertFileExists($result);
            $this->assertEquals($expectedFilename, $result);
            $this->assertEventFired(LINK_SCANNER . '.resultsExported', $this->EventManager);
        }
    }

    /**
     * Test for `export()` method, with a no existing file
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp /^Failed to export results to file `[\\\/\w\d:\-]+` with message `failed to open stream: No such file or directory`$/
     * @test
     */
    public function testExportNoExistingFile()
    {
        $this->LinkScanner->scan()->export(TMP . 'noExistingDir' . DS . 'result');
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
        $this->LinkScanner->setConfig('externalLinks', false);
        $this->LinkScanner->setConfig('maxDepth', 1);
        $this->LinkScanner->Client->setConfig('timeout', 100);
        $this->LinkScanner->scan();
        $filename = $this->LinkScanner->export();

        $resultAsObject = (new LinkScanner)->import($filename);
        $resultAsStatic = LinkScanner::import($filename);

        foreach ([$resultAsObject, $resultAsStatic] as $result) {
            $this->assertInstanceof(LinkScanner::class, $result);

            //Checks configuration
            $this->assertEquals(false, $result->getConfig('externalLinks'));
            $this->assertEquals(1, $result->getConfig('maxDepth'));
            $this->assertEquals(100, $result->Client->getConfig('timeout'));

            //Checks the event is fired only on the new object. Then, it unsets both
            //  event lists, so that the objects comparison will run
            $this->assertEventNotFired(LINK_SCANNER . '.resultsImported', $this->getEventManager());
            $this->assertEventFired(LINK_SCANNER . '.resultsImported', $this->getEventManager($result));
            $this->getEventManager()->unsetEventList();
            $this->getEventManager($result)->unsetEventList();

            //Gets properties from both client, fixes properties of the `Client`
            //  instances and performs the comparison
            $originalProperties = $this->getProperties($this->LinkScanner);
            $resultProperties = $this->getProperties($result);
            $originalProperties['Client'] = $this->getProperties($this->LinkScanner->Client);
            $resultProperties['Client'] = $this->getProperties($result->Client);
            unset($originalProperties['Client']['_adapter'], $resultProperties['Client']['_adapter']);
            $this->assertEquals($resultProperties, $originalProperties);
        }
    }

    /**
     * Test for `import()` method, with a no existing file
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp /^Failed to import results from file `[\\\/\w\d:\-]+` with message `failed to open stream: No such file or directory`$/
     * @test
     */
    public function testImportNoExistingFile()
    {
        LinkScanner::import(TMP . 'noExistingDir' . DS . 'result');
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

        //Results contain both internal and external urls
        $internalLinks = $this->LinkScanner->ResultScan->match(['external' => false]);
        $externalLinks = $this->LinkScanner->ResultScan->match(['external' => true]);
        $this->assertNotEmpty($internalLinks->toList());
        $this->assertNotEmpty($externalLinks->toList());
        $this->assertEquals($this->LinkScanner->ResultScan->count(), $internalLinks->count() + $externalLinks->count());

        //Takes a random url from the last scan and adds it to the url to
        //  exclude on the next scan
        $randomUrl = $this->LinkScanner->ResultScan->extract('url')->sample(1)->first();
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl, null, $this->getClientReturnsSampleResponse());
        $this->LinkScanner->setConfig('excludeLinks', preg_quote($randomUrl, '/'))->scan();
        $this->assertCount(0, $this->LinkScanner->ResultScan->match(['url' => $randomUrl]));

        //Tries again with two urls to exclude on the next scan
        $randomsUrls = $this->LinkScanner->ResultScan->extract('url')->sample(2)->toList();
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl, null, $this->getClientReturnsSampleResponse());
        $this->LinkScanner->setConfig('excludeLinks', [preg_quote($randomsUrls[0], '/'), preg_quote($randomsUrls[1], '/')]);
        $this->LinkScanner->scan();
        $this->assertCount(0, $this->LinkScanner->ResultScan->match(['url' => $randomsUrls[0]]));
        $this->assertCount(0, $this->LinkScanner->ResultScan->match(['url' => $randomsUrls[1]]));

        //Disables external links and tries again
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl, null, $this->getClientReturnsSampleResponse());
        $result = $this->LinkScanner->setConfig('maxDepth', 2)->setConfig('externalLinks', false)->scan();
        $newInternalLinks = $this->LinkScanner->ResultScan->match(['external' => false]);
        $newExternalLinks = $this->LinkScanner->ResultScan->match(['external' => true]);
        $this->assertEquals($newInternalLinks, $internalLinks);
        $this->assertEmpty($newExternalLinks->toList());

        foreach ($this->LinkScanner->ResultScan as $item) {
            if (!$item->external) {
                $this->assertTextStartsWith($this->fullBaseUrl, $item->url);
            } else {
                $this->assertTextStartsNotWith($this->fullBaseUrl, $item->url);
            }
            $this->assertContains($item->code, [200, 500]);
            $this->assertEquals($item->type, 'text/html');
        }

        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->setMethods(['_recursiveScan'])
            ->getMock();

        $LinkScanner->expects($this->once())
             ->method('_recursiveScan');

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

        $params = ['controller' => 'Pages', 'action' => 'display', 'home'];
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests($params);
        $LinkScanner->scan();

        $expectedDebug = [
            'Scanning http://localhost',
            'Found link: http://google.it',
            'Scanning http://google.it',
            'Found link: http://localhost/pages/first_page',
            'Scanning http://localhost/pages/first_page',
            'Found link: http://localhost/favicon.ico',
            'Scanning http://localhost/favicon.ico',
            'Found link: http://localhost/css/default.css',
            'Scanning http://localhost/css/default.css',
            'Found link: http://localhost/js/default.js',
            'Scanning http://localhost/js/default.js',
            'Found link: http://localhost/pages/second_page',
            'Scanning http://localhost/pages/second_page',
        ];
        $this->assertEquals($expectedDebug, $this->debug);

        //Results contain both internal and external urls
        $internalLinks = $LinkScanner->ResultScan->match(['external' => false])->extract('url');
        $externalLinks = $LinkScanner->ResultScan->match(['external' => true])->extract('url');
        $this->assertEquals([
            'http://localhost',
            'http://localhost/pages/first_page',
            'http://localhost/favicon.ico',
            'http://localhost/css/default.css',
            'http://localhost/js/default.js',
            'http://localhost/pages/second_page',
        ], $internalLinks->toList());
        $this->assertEquals(['http://google.it'], $externalLinks->toList());

        $LinkScanner = $this->getLinkScannerClientReturnsFromTests($params);
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
     * Test for `scan()` method, with no other links to scan
     * @test
     */
    public function testScanNoOtherLinks()
    {
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests(['controller' => 'Pages', 'action' => 'display', 'nolinks']);
        $LinkScanner->scan();

        $this->assertEventNotFired(LINK_SCANNER . '.foundLinkToBeScanned', $this->getEventManager($LinkScanner));
    }

    /**
     * Test for `scan()` method, with a response that is not ok
     * @test
     */
    public function testScanResponseNotOk()
    {
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests('http://localhost/noExisting');
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
            'http://fullBaseUrl.com' => 'http://fullBaseUrl.com',
            'http://fullBaseUrl.com/' => 'http://fullBaseUrl.com',
            'http://fullBaseUrl.com/site' => 'http://fullBaseUrl.com/site',
            'http://fullBaseUrl.com/site/' => 'http://fullBaseUrl.com/site',
            'http://www.fullBaseUrl.com/site' => 'http://fullBaseUrl.com/site',
            'https://fullBaseUrl.com' => 'https://fullBaseUrl.com',
            'https://www.fullBaseUrl.com' => 'https://fullBaseUrl.com',
            'ftp://fullBaseUrl.com' => 'ftp://fullBaseUrl.com',
            'ftp://www.fullBaseUrl.com' => 'ftp://fullBaseUrl.com',
        ] as $fullBaseUrl => $expectedFullBaseUrl) {
            $result = $this->LinkScanner->setFullBaseUrl($fullBaseUrl);
            $this->assertInstanceof(LinkScanner::class, $result);
            $this->assertEquals($expectedFullBaseUrl, $this->getProperty($this->LinkScanner, 'fullBaseUrl'));
            $this->assertEquals('fullBaseUrl.com', $this->getProperty($this->LinkScanner, 'hostname'));
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
