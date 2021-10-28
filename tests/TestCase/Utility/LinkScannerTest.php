<?php
declare(strict_types=1);

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
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Event\EventList;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Exception;
use LinkScanner\ResultScan;
use LinkScanner\TestSuite\IntegrationTestTrait;
use LinkScanner\TestSuite\TestCase;
use LinkScanner\Utility\LinkScanner;
use PHPUnit\Framework\Error\Deprecated;
use RuntimeException;
use Zend\Diactoros\Stream;

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
     * @var \LinkScanner\Utility\LinkScanner|(\LinkScanner\Utility\LinkScanner&\PHPUnit\Framework\MockObject\MockObject)
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
     * Called before every test method
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->debug = [];
        $this->fullBaseUrl = 'http://google.com';
        $this->LinkScanner = new LinkScanner($this->getClientReturnsSampleResponse());
        $this->LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl);
        $this->LinkScanner->Client->setConfig('timeout', 5);
        $this->EventManager = $this->getEventManager();
    }

    /**
     * Test for `__construct()` method
     * @test
     */
    public function testConstruct(): void
    {
        $config = [
            'cache' => false,
            'externalLinks' => false,
            'followRedirects' => true,
            'fullBaseUrl' => 'http://localhost',
            'maxDepth' => 2,
        ];
        $expected = array_merge($this->LinkScanner->getConfig(), $config);

        (new PhpConfig())->dump('link_scanner', ['LinkScanner' => $config]);
        $this->assertSame($expected, (new LinkScanner())->getConfig());

        @unlink(CONFIG . 'link_scanner.php');
    }

    /**
     * Test for `_getResponse()` method
     * @test
     */
    public function testGetResponse(): void
    {
        $getResponseMethod = function (string $url): Response {
            return $this->invokeMethod($this->LinkScanner, '_getResponse', [$url]);
        };
        $getResponseFromCache = function (string $url): ?Response {
            $response = Cache::read(sprintf('response_%s', md5(serialize($url))), 'LinkScanner');

            if ($response && is_array($response)) {
                [$response, $body] = $response;

                $stream = new Stream('php://memory', 'wb+');
                $stream->write($body);
                $stream->rewind();
                $response = $response->withBody($stream);
            }

            return $response;
        };

        $response = $getResponseMethod($this->fullBaseUrl);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('content-type'));
        $responseFromCache = $getResponseFromCache($this->fullBaseUrl);
        $this->assertNotEmpty($responseFromCache);
        $this->assertInstanceof(Response::class, $responseFromCache);

        //With disabled cache
        Cache::clear('LinkScanner');
        $this->LinkScanner->setConfig('cache', false);
        $getResponseMethod($this->fullBaseUrl);
        $this->assertEmpty($getResponseFromCache($this->fullBaseUrl));

        $this->LinkScanner = $this->getLinkScannerClientReturnsFromTests();
        $this->LinkScanner->setConfig('cache', true);
        foreach ([
            'http://localhost/pages/nolinks' => 200,
            'http://localhost/pages/home' => 200,
            'http://localhost/pages/noexisting' => 500,
        ] as $url => $expectedStatusCode) {
            $response = $getResponseMethod($url);
            $this->assertEquals($expectedStatusCode, $response->getStatusCode());
            $this->assertStringStartsWith('text/html', $response->getHeaderLine('content-type'));

            $responseFromCache = $getResponseFromCache($url);
            if ($response->isOk()) {
                $this->assertNotEmpty($responseFromCache);
                $this->assertInstanceof(Response::class, $responseFromCache);
            }
        }

        //`Client::get()` method throws an exception
        /** @var \Cake\Http\Client&\PHPUnit\Framework\MockObject\MockObject $Client */
        $Client = $this->getMockBuilder(Client::class)
            ->setMethods(['get'])
            ->getMock();

        $Client->method('get')->will($this->throwException(new Exception()));

        $this->LinkScanner = new LinkScanner($Client);
        $this->LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl);
        $this->assertEquals(404, $getResponseMethod('/noExisting')->getStatusCode());

        //Does not suppress PHPUnit exceptions, which are throwned anyway
        $this->expectDeprecation();
        $Client->method('get')->will($this->throwException(new Deprecated('This is deprecated', 0, __FILE__, __LINE__)));
        $this->LinkScanner = new LinkScanner($Client);
        $getResponseMethod('/noExisting');
    }

    /**
     * Test for `export()` method
     * @test
     */
    public function testExport(): void
    {
        $this->LinkScanner->setConfig('maxDepth', 1)->scan();
        $target = $this->LinkScanner->getConfig('target');

        //Filename can be `null`, relative or absolute
        foreach ([
            null => $target . DS . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime,
            'example' => $target . DS . 'example',
            TMP . 'example' => TMP . 'example',
        ] as $filename => $expectedFilename) {
            $result = $this->LinkScanner->export($filename);
            $this->assertFileExists($result);
            $this->assertEquals($expectedFilename, $result);
            $this->assertEventFired('LinkScanner.resultsExported', $this->EventManager);

            ($this->EventManager->getEventList() ?: new EventList())->flush();
        }

        //Without the scan being performed
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no result to export. Perhaps the scan was not performed?');
        (new LinkScanner())->export();
    }

    /**
     * Test for `import()` method
     * @test
     */
    public function testImport(): void
    {
        $this->LinkScanner->Client->setConfig('timeout', 100);
        $this->LinkScanner->setConfig('externalLinks', false)->setConfig('maxDepth', 1)->scan();
        $config = $this->LinkScanner->getConfig();
        $filename = basename($this->LinkScanner->export());
        $result = (new LinkScanner())->import($filename);
        $this->assertInstanceof(LinkScanner::class, $result);
        $this->assertEquals($config, $result->getConfig());
        $this->assertEquals(100, $result->Client->getConfig('timeout'));

        //Checks the event is fired only on the new object. Then, it flushes
        //  both event lists, so that the objects comparison will run
        $this->assertEventNotFired('LinkScanner.resultsImported', $this->EventManager);
        $this->assertEventFired('LinkScanner.resultsImported', $this->getEventManager($result));
        ($this->EventManager->getEventList() ?: new EventList())->flush();
        ($this->getEventManager($result)->getEventList() ?: new EventList())->flush();

        //Gets properties from both client, fixes properties of the `Client`
        //  instances and performs the comparison
        $expectedProperties = $this->getProperties($this->LinkScanner->Client);
        $resultProperties = $this->getProperties($result->Client);
        foreach (['_adapter', '__phpunit_invocationMocker', '__phpunit_originalObject', '__phpunit_configurable'] as $key) {
            unset($expectedProperties[$key], $resultProperties[$key]);
        }
        $expectedProperties = ['Client' => $expectedProperties] + $this->getProperties($this->LinkScanner);
        $resultProperties = ['Client' => $resultProperties] + $this->getProperties($result);
        $this->assertEquals($expectedProperties, $resultProperties);

        //With a no existing file
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to import results from file `' . TMP . 'noExistingDir' . DS . 'result` with message `failed to open stream: No such file or directory`');
        $this->LinkScanner->import(TMP . 'noExistingDir' . DS . 'result');
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan(): void
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
        $this->assertFalse($internalLinks->isEmpty());
        $this->assertFalse($externalLinks->isEmpty());
        $this->assertEquals($this->LinkScanner->ResultScan->count(), $internalLinks->count() + $externalLinks->count());

        //Takes the last url from the last scan and adds it to the url to
        //  exclude on the next scan
        $randomUrl = $this->LinkScanner->ResultScan->extract('url')->last();
        $LinkScanner = new LinkScanner($this->getClientReturnsSampleResponse());
        $LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl)
            ->setConfig('excludeLinks', '#' . preg_quote($randomUrl) . '#')
            ->scan();
        $this->assertCount(0, $LinkScanner->ResultScan->match(['url' => $randomUrl]));

        //Tries again with two urls to exclude on the next scan
        $randomsUrls = $this->LinkScanner->ResultScan->extract('url')->take(2, 1)->toList();
        $LinkScanner = new LinkScanner($this->getClientReturnsSampleResponse());
        $LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl)
            ->setConfig('excludeLinks', '#(' . implode('|', array_map('preg_quote', $randomsUrls)) . ')#')
            ->scan();
        $this->assertCount(0, $LinkScanner->ResultScan->match(['url' => $randomsUrls[0]]));
        $this->assertCount(0, $LinkScanner->ResultScan->match(['url' => $randomsUrls[1]]));

        //Disables external links and tries again
        $LinkScanner = new LinkScanner($this->getClientReturnsSampleResponse());
        $LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl)
            ->setConfig('maxDepth', 2)
            ->setConfig('externalLinks', false)
            ->scan();
        $newInternalLinks = $LinkScanner->ResultScan->match(['external' => false]);
        $newExternalLinks = $LinkScanner->ResultScan->match(['external' => true]);
        $this->assertEquals($newInternalLinks, $internalLinks);
        $this->assertEmpty($newExternalLinks->toList());

        $hostname = get_hostname_from_url($this->fullBaseUrl);

        foreach ($LinkScanner->ResultScan as $item) {
            $this->assertMatchesRegularExpression(sprintf('/^https?:\/\/%s/', preg_quote($hostname)), $item->get('url'));
            $this->assertContains($item->get('code'), [200, 500]);
            $this->assertStringStartsWith('text/html', $item->get('type'));
        }

        /** @var \LinkScanner\Utility\LinkScanner&\PHPUnit\Framework\MockObject\MockObject $LinkScanner */
        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->setMethods(['_recursiveScan'])
            ->getMock();

        $LinkScanner->expects($this->once())
             ->method('_recursiveScan');

        $LinkScanner->scan();

        //The lock file alread exists
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lock file `' . $LinkScanner->lockFile . '` already exists, maybe a scan is already in progress. If not, remove it manually');
        file_put_contents($LinkScanner->lockFile, null);
        (new LinkScanner())->scan();
    }

    /**
     * Test for `scan()` method, from tests
     * @test
     */
    public function testScanFromTests(): void
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
            'Found link: http://localhost/pages/nohtml',
            'Scanning http://localhost/pages/nohtml',
            'Found link: http://localhost/pages/redirect',
            'Scanning http://localhost/pages/redirect',
            'Found link: http://localhost/pages/sameredirect',
            'Scanning http://localhost/pages/sameredirect',
        ];
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests();
        $LinkScanner->scan();
        $this->assertEquals($expectedDebug, $this->debug);

        //Results contain different status code
        $this->assertFalse($LinkScanner->ResultScan->match(['code' => 200])->isEmpty());
        $this->assertFalse($LinkScanner->ResultScan->match(['code' => 302])->isEmpty());
        $this->assertFalse($LinkScanner->ResultScan->match(['code' => 404])->isEmpty());

        //Results contain both internal and external urls
        $expectedInternal = [
            'http://localhost',
            'http://localhost/pages/first_page',
            'http://localhost/favicon.ico',
            'http://localhost/css/default.css',
            'http://localhost/js/default.js',
            'http://localhost/pages/second_page',
            'http://localhost/pages/nohtml',
            'http://localhost/pages/redirect',
            'http://localhost/pages/sameredirect',
        ];
        $expectedExternal = ['http://google.it'];
        $internalLinks = $LinkScanner->ResultScan->match(['external' => false])->extract('url');
        $externalLinks = $LinkScanner->ResultScan->match(['external' => true])->extract('url');
        $this->assertEquals($expectedInternal, $internalLinks->toList());
        $this->assertEquals($expectedExternal, $externalLinks->toList());

        $this->debug = [];

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
            'Found link: http://localhost/pages/nohtml',
            'Scanning http://localhost/pages/nohtml',
            'Found link: http://localhost/pages/redirect',
            'Scanning http://localhost/pages/redirect',
            'Found redirect: http://localhost/pages/third_page',
            'Scanning http://localhost/pages/third_page',
            'Found link: http://localhost/pages/sameredirect',
            'Scanning http://localhost/pages/sameredirect',
        ];
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests();
        $LinkScanner->setConfig('followRedirects', true)->scan();
        $this->assertEquals($expectedDebug, $this->debug);

        array_pop($expectedInternal);
        array_pop($expectedInternal);
        $expectedInternal[] = 'http://localhost/pages/third_page';
        $internalLinks = $LinkScanner->ResultScan->match(['external' => false])->extract('url');
        $externalLinks = $LinkScanner->ResultScan->match(['external' => true])->extract('url');
        $this->assertEquals($expectedInternal, $internalLinks->toList());
        $this->assertEquals($expectedExternal, $externalLinks->toList());

        $LinkScanner = $this->getLinkScannerClientReturnsFromTests();
        $LinkScanner->setConfig('maxDepth', 1)->scan();
        $this->assertCount(1, $LinkScanner->ResultScan);
        $item = $LinkScanner->ResultScan->first();
        $this->assertEquals($item->get('code'), 200);
        $this->assertFalse($item->get('external'));
        $this->assertNull($item->get('referer'));
        $this->assertStringStartsWith('text/html', $item->get('type'));
        $this->assertEquals($item->get('url'), 'http://localhost');

        $LinkScanner = $this->getLinkScannerClientReturnsFromTests();
        $LinkScanner->setConfig('exportOnlyBadResults', true)->scan();
        $LinkScanner->export();
        $this->assertTrue($LinkScanner->ResultScan->match(['code' => 200])->isEmpty());
        $this->assertTrue($LinkScanner->ResultScan->match(['code' => 302])->isEmpty());
        $this->assertFalse($LinkScanner->ResultScan->match(['code' => 404])->isEmpty());
    }

    /**
     * Test for `scan()` method, with no other links to scan
     * @test
     */
    public function testScanNoOtherLinks(): void
    {
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests('http://localhost/pages/nolinks');

        $this->assertEventNotFired('LinkScanner.foundLinkToBeScanned', $this->getEventManager($LinkScanner->scan()));
    }

    /**
     * Test for `scan()` method, with a response that is not ok
     * @test
     */
    public function testScanResponseNotOk(): void
    {
        $LinkScanner = $this->getLinkScannerClientReturnsFromTests('http://localhost/noExisting');
        $EventManager = $this->getEventManager($LinkScanner);
        $LinkScanner->scan();

        $this->assertEventFired('LinkScanner.responseNotOk', $EventManager);
    }
}
