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
use Exception;
use InvalidArgumentException;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ResultScan;
use LinkScanner\TestSuite\IntegrationTestTrait;
use LinkScanner\TestSuite\TestCase;
use LinkScanner\Utility\LinkScanner;
use RuntimeException;

/**
 * LinkScannerTest class
 */
class LinkScannerTest extends TestCase
{
    use IntegrationTestTrait;

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

        $this->debug = [];
        $this->fullBaseUrl = 'http://google.com';
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl);
        $this->LinkScanner->Client = $this->getClientReturnsSampleResponse();
        $this->EventManager = $this->getEventManager();
    }

    /**
     * Test for `_getResponse()` method
     * @test
     */
    public function testGetResponse()
    {
        $getResponseMethod = function ($url) {
            return $this->invokeMethod($this->LinkScanner, '_getResponse', [$url]);
        };
        $getResponseFromCache = function ($url) {
            return Cache::read(sprintf('response_%s', md5(serialize($url))), 'LinkScanner');
        };

        $response = $getResponseMethod($this->fullBaseUrl);
        $this->assertInstanceof(ScanResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getContentType());
        $responseFromCache = $getResponseFromCache($this->fullBaseUrl);
        $this->assertNotEmpty($responseFromCache);
        $this->assertInstanceof(ScanResponse::class, $responseFromCache);

        //With disabled cache
        Cache::clearAll();
        $this->LinkScanner->setConfig('cache', false);
        $getResponseMethod($this->fullBaseUrl);
        $this->assertEmpty($getResponseFromCache($this->fullBaseUrl));

        $this->LinkScanner = $this->getLinkScannerClientReturnsFromTests();
        $this->LinkScanner->setConfig('cache', true);

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

        //`Client::get()` method throws an exception
        $this->LinkScanner->Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        $this->LinkScanner->Client->method('get')->will($this->throwException(new Exception));

        $response = $getResponseMethod('/noExisting');
        $this->assertInstanceof(ScanResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
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
            null => $this->LinkScanner->getConfig('target') . DS . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime,
            'example' => $this->LinkScanner->getConfig('target') . DS . 'example',
            TMP . 'example' => TMP . 'example',
        ] as $filenameWhereToExport => $expectedFilename) {
            $result = $this->LinkScanner->export($filenameWhereToExport);
            $this->assertFileExists($result);
            $this->assertEquals($expectedFilename, $result);
            $this->assertEventFired('LinkScanner.resultsExported', $this->EventManager);

            $this->EventManager->getEventList()->flush();
        }

        //Without the scan being performed
        $this->assertException(RuntimeException::class, function () {
            (new LinkScanner)->export();
        }, 'There is no result to export. Perhaps the scan was not performed?');

        //With a no existing file
        $noExistingFile = TMP . 'noExistingDir' . DS . 'result';
        $this->assertException(RuntimeException::class, function () use ($noExistingFile) {
            $this->LinkScanner->scan()->export($noExistingFile);
        }, 'Failed to export results to file `' . $noExistingFile . '` with message `failed to open stream: No such file or directory`');
    }

    /**
     * Test for `import()` method
     * @test
     */
    public function testImport()
    {
        $this->LinkScanner->setConfig('externalLinks', false)->setConfig('maxDepth', 1);
        $this->LinkScanner->Client->setConfig('timeout', 100);
        $this->LinkScanner->scan();
        $filename = $this->LinkScanner->export();

        $resultAsObject = (new LinkScanner)->import($filename);
        $resultAsStatic = LinkScanner::import($filename);

        foreach ([$resultAsObject, $resultAsStatic] as $result) {
            $this->assertInstanceof(LinkScanner::class, $result);

            $this->assertEquals([
                'cache' => true,
                'excludeLinks' => ['\{.*\}', 'javascript:'],
                'externalLinks' => false,
                'followRedirects' => false,
                'maxDepth' => 1,
                'lockFile' => true,
                'target' => TMP,
            ], $result->getConfig());
            $this->assertEquals(100, $result->Client->getConfig('timeout'));

            //Checks the event is fired only on the new object. Then, it flushes
            //  both event lists, so that the objects comparison will run
            $this->assertEventNotFired('LinkScanner.resultsImported', $this->EventManager);
            $this->assertEventFired('LinkScanner.resultsImported', $this->getEventManager($result));
            $this->EventManager->getEventList()->flush();
            $this->getEventManager($result)->getEventList()->flush();

            //Gets properties from both client, fixes properties of the `Client`
            //  instances and performs the comparison
            $expectedClientProperties = $this->getProperties($this->LinkScanner->Client);
            $resultClientProperties = $this->getProperties($result->Client);
            foreach (['_adapter', '__phpunit_invocationMocker', '__phpunit_originalObject', '__phpunit_configurable'] as $key) {
                unset($expectedClientProperties[$key], $resultClientProperties[$key]);
            }
            $expectedProperties = ['Client' => $expectedClientProperties] + $this->getProperties($this->LinkScanner);
            $resultProperties = ['Client' => $resultClientProperties] + $this->getProperties($result);
            $this->assertEquals($expectedProperties, $resultProperties);
        }

        //With a no existing file
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to import results from file `' . TMP . 'noExistingDir' . DS . 'result` with message `failed to open stream: No such file or directory`');
        LinkScanner::import(TMP . 'noExistingDir' . DS . 'result');
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $result = $this->LinkScanner->setConfig('maxDepth', 2)->scan();

        foreach ([
            'afterScanUrl',
            'beforeScanUrl',
            'foundLinkToBeScanned',
            'scanStarted',
            'scanCompleted',
        ] as $eventName) {
            $this->assertEventFired('LinkScanner.' . $eventName, $this->EventManager);
        }

        foreach (['foundRedirect', 'resultsExported'] as $eventName) {
            $this->assertEventNotFired('LinkScanner.' . $eventName, $this->EventManager);
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

        //Takes the last url from the last scan and adds it to the url to
        //  exclude on the next scan
        $randomUrl = $this->LinkScanner->ResultScan->extract('url')->last();
        $LinkScanner = new LinkScanner($this->fullBaseUrl);
        $LinkScanner->Client = $this->getClientReturnsSampleResponse();
        $LinkScanner->setConfig('excludeLinks', preg_quote($randomUrl, '/'))->scan();
        $this->assertCount(0, $LinkScanner->ResultScan->match(['url' => $randomUrl]));

        //Tries again with two urls to exclude on the next scan
        $randomsUrls = $this->LinkScanner->ResultScan->extract('url')->take(2, 1)->toList();
        $LinkScanner = new LinkScanner($this->fullBaseUrl);
        $LinkScanner->Client = $this->getClientReturnsSampleResponse();
        $LinkScanner->setConfig('excludeLinks', [preg_quote($randomsUrls[0], '/'), preg_quote($randomsUrls[1], '/')]);
        $LinkScanner->scan();
        $this->assertCount(0, $LinkScanner->ResultScan->match(['url' => $randomsUrls[0]]));
        $this->assertCount(0, $LinkScanner->ResultScan->match(['url' => $randomsUrls[1]]));

        //Disables external links and tries again
        $LinkScanner = new LinkScanner($this->fullBaseUrl);
        $LinkScanner->Client = $this->getClientReturnsSampleResponse();
        $result = $LinkScanner->setConfig('maxDepth', 2)->setConfig('externalLinks', false)->scan();
        $newInternalLinks = $LinkScanner->ResultScan->match(['external' => false]);
        $newExternalLinks = $LinkScanner->ResultScan->match(['external' => true]);
        $this->assertEquals($newInternalLinks, $internalLinks);
        $this->assertEmpty($newExternalLinks->toList());

        $hostname = get_hostname_from_url($this->fullBaseUrl);

        foreach ($LinkScanner->ResultScan as $item) {
            if (!$item->external) {
                $this->assertRegexp(sprintf('/^https?:\/\/%s/', preg_quote($hostname)), $item->url);
            } else {
                $this->assertTextStartsNotWith('http://' . $hostname, $item->url);
                $this->assertTextStartsNotWith('https://' . $hostname, $item->url);
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

        //The lock file alread exists
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lock file `' . LINK_SCANNER_LOCK_FILE . '` already exists, maybe a scan is already in progress. If not, remove it manually');
        file_put_contents(LINK_SCANNER_LOCK_FILE, null);
        (new LinkScanner)->scan();
    }

    /**
     * Test for `scan()` method, from tests
     * @test
     */
    public function testScanFromTests()
    {
        //Sets events. They will add some output to the `$this->debug` property
        $this->getEventManager()->instance()
            ->on('LinkScanner.beforeScanUrl', function () {
                $this->debug[] = sprintf('Scanning %s', func_get_arg(1));
            })
            ->on('LinkScanner.foundLinkToBeScanned', function () {
                $this->debug[] = sprintf('Found link: %s', func_get_arg(1));
            })
            ->on('LinkScanner.foundRedirect', function () {
                $this->debug[] = sprintf('Found redirect: %s', func_get_arg(1));
            });

        $params = ['controller' => 'Pages', 'action' => 'display', 'home'];
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests($params);
        $LinkScanner->scan();

        $expectedDebug = [
            'Scanning http://localhost/',
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
            'Found link: http://localhost/pages/redirect',
            'Scanning http://localhost/pages/redirect',
        ];
        $this->assertEquals($expectedDebug, $this->debug);

        //Results contain both internal and external urls
        $expectedInternaLinks = [
            'http://localhost/',
            'http://localhost/pages/first_page',
            'http://localhost/favicon.ico',
            'http://localhost/css/default.css',
            'http://localhost/js/default.js',
            'http://localhost/pages/second_page',
            'http://localhost/pages/redirect',
        ];
        $expectedExternalLinks = ['http://google.it'];
        $internalLinks = $LinkScanner->ResultScan->match(['external' => false])->extract('url');
        $externalLinks = $LinkScanner->ResultScan->match(['external' => true])->extract('url');
        $this->assertEquals($expectedInternaLinks, $internalLinks->toList());
        $this->assertEquals($expectedExternalLinks, $externalLinks->toList());

        $this->debug = [];

        $expectedDebug = array_merge($expectedDebug, [
            'Found redirect: http://localhost/pages/third_page',
            'Scanning http://localhost/pages/third_page',
        ]);
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests($params);
        $LinkScanner->setConfig('followRedirects', true)->scan();
        $this->assertEquals($expectedDebug, $this->debug);

        $expectedInternaLinks[array_search('http://localhost/pages/redirect', $expectedInternaLinks)] = 'http://localhost/pages/third_page';
        $internalLinks = $LinkScanner->ResultScan->match(['external' => false])->extract('url');
        $externalLinks = $LinkScanner->ResultScan->match(['external' => true])->extract('url');
        $this->assertEquals($expectedInternaLinks, $internalLinks->toList());
        $this->assertEquals($expectedExternalLinks, $externalLinks->toList());

        $LinkScanner = $this->getLinkScannerClientReturnsFromTests($params);
        $LinkScanner->setConfig('maxDepth', 1)->scan();
        $this->assertCount(1, $LinkScanner->ResultScan);
        $item = $LinkScanner->ResultScan->first();
        $this->assertEquals($item->code, 200);
        $this->assertFalse($item->external);
        $this->assertNull($item->referer);
        $this->assertEquals($item->type, 'text/html');
        $this->assertEquals($item->url, 'http://localhost/');
    }

    /**
     * Test for `scan()` method, with no other links to scan
     * @test
     */
    public function testScanNoOtherLinks()
    {
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests(['controller' => 'Pages', 'action' => 'display', 'nolinks']);
        $LinkScanner->scan();

        $this->assertEventNotFired('LinkScanner.foundLinkToBeScanned', $this->getEventManager($LinkScanner));
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

        $this->assertEventFired('LinkScanner.' . 'responseNotOk', $EventManager);
    }

    /**
     * Test for `setFullBaseUrl()` method
     * @test
     */
    public function testSetFullBaseUrl()
    {
        foreach ([
            'http://fullBaseUrl.com',
            'http://fullBaseUrl.com/site',
            'https://fullBaseUrl.com',
            'ftp://fullBaseUrl.com',
        ] as $fullBaseUrl) {
            $result = $this->LinkScanner->setFullBaseUrl($fullBaseUrl);
            $this->assertInstanceof(LinkScanner::class, $result);
            $this->assertEquals($fullBaseUrl, $result->fullBaseUrl);
            $this->assertEquals('fullBaseUrl.com', $result->hostname);
        }

        //With a no-string or an invalid string
        foreach (['invalid', ['invalid']] as $fullBaseUrl) {
            $this->assertException(InvalidArgumentException::class, function () use ($fullBaseUrl) {
                $this->LinkScanner->setFullBaseUrl($fullBaseUrl);
            }, 'Invalid url `invalid`');
        }
    }
}
