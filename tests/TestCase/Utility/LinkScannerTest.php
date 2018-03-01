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

use Cake\Event\EventList;
use Cake\TestSuite\IntegrationTestCase;
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
    }

    /**
     * Teardown any static object changes and restore them
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        foreach (glob(TMP . "results_*") as $file) {
            unlink($file);
        }
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

        //Arrays always returns `false`
        $this->assertFalse($isExternalUrlMethod(['controller' => 'Pages', 'action' => 'display', 'nolinks']));

        foreach ([
            '//google.com',
            '//google.com/',
            'http://google.com',
            'http://google.com/',
            'http://www.google.com',
            'http://www.google.com/',
            'http://www.google.com/page.html',
            'https://google.com',
            'relative.html',
            '/relative.html',
        ] as $url) {
            $this->assertFalse($isExternalUrlMethod($url));
        }

        foreach ([
            '//site.com',
            'http://site.com',
            'http://www.site.com',
            'http://subdomain.google.com',
        ] as $url) {
            $this->assertTrue($isExternalUrlMethod($url));
        }
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

        $params = ['controller' => 'Pages', 'action' => 'display', 'nolinks'];
        $result = $getResponseMethod($params);
        $this->assertInstanceof('LinkScanner\Http\Client\ScanResponse', $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringStartsWith('text/html', $result->getContentType());

        $params = ['controller' => 'Pages', 'action' => 'display', 'home'];
        $result = $getResponseMethod($params);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringStartsWith('text/html', $result->getContentType());

        $params = ['controller' => 'Pages', 'action' => 'display', 'noexisting'];
        $result = $getResponseMethod($params);
        $this->assertEquals(500, $result->getStatusCode());
        $this->assertStringStartsWith('text/html', $result->getContentType());

        $this->LinkScanner = $this->getLinkScannerClientReturnsSampleResponse();

        $url = 'http://www.google.it';
        $result = $getResponseMethod($url);
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

        foreach (['currentDepth' => 1, 'externalLinks' => 'value', 'endTime' => 2, 'startTime' => 1] as $name => $value) {
            $this->setProperty($this->LinkScanner, $name, $value);
        }

        $this->LinkScanner = $this->LinkScanner->reset();
        $this->assertInstanceof(LinkScanner::class, $this->LinkScanner);

        $this->assertInstanceof(ResultScan::class, $this->LinkScanner->ResultScan);
        $this->assertEmpty($this->LinkScanner->ResultScan->toArray());

        $this->assertEquals([], $this->getProperty($this->LinkScanner, 'externalLinks'));

        foreach (['currentDepth', 'endTime', 'startTime'] as $property) {
            $this->assertEquals(0, $this->LinkScanner->$property);
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

        $filename = TMP . 'results_as_array';

        $result = $this->LinkScanner->export($filename);
        $this->assertFileExists($result);
        $this->assertEquals($filename, $result);
    }

    /**
     * Test for `export()` method, with a no existing file
     * @expectedException LogicException
     * @expectedExceptionMessage Failed to export results to file `/noExisting`
     * @test
     */
    public function testExportNoExistingFile()
    {
        $this->LinkScanner->setMaxDepth(1)->scan()->export('/noExisting');
    }

    /**
     * Test for `import()` method
     * @test
     */
    public function testImport()
    {
        $this->LinkScanner->setMaxDepth(1)->scan();

        $expectedLinkScanner = $this->LinkScanner;
        $expectedResultScan = $this->LinkScanner->ResultScan;

        $result = $this->LinkScanner->import($this->LinkScanner->export());

        $this->assertEquals($expectedLinkScanner, $result);
        $this->assertEquals($expectedResultScan, $result->ResultScan);
    }

    /**
     * Test for `import()` method, with a no existing file
     * @expectedException LogicException
     * @expectedExceptionMessage Failed to import results from file `/noExisting`
     * @test
     */
    public function testImportNoExistingFile()
    {
        $this->LinkScanner->import('/noExisting');
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $result = $this->LinkScanner->setMaxDepth(1)->scan();

        $this->assertInstanceof(LinkScanner::class, $result);
        $this->assertTrue(is_int($this->LinkScanner->startTime));
        $this->assertTrue(is_int($this->LinkScanner->endTime));

        $this->assertInstanceof(ResultScan::class, $this->LinkScanner->ResultScan);
        $this->assertCount(9, $this->LinkScanner->ResultScan);

        foreach ($this->LinkScanner->ResultScan->toArray() as $item) {
            $this->assertFalse($item['external']);
            $this->assertContains($item['code'], [200, 500]);
            $this->assertContains($item['type'], ['text/html; charset=ISO-8859-1']);
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
     * Test for events triggered by the `scan()` method
     * @test
     */
    public function testScanEvents()
    {
        //Enables event tracking
        $eventManager = $this->LinkScanner->getEventManager();
        $eventManager->setEventList(new EventList);

        $this->LinkScanner->setMaxDepth(1)->scan();

        foreach (['scanStarted', 'scanCompleted', 'beforeScanUrl', 'afterScanUrl', 'foundLinksToBeScanned'] as $eventName) {
            $this->assertEventFired(LINK_SCANNER . '.' . $eventName, $eventManager);
        }
    }

    /**
     * Test for `scan()` method, with no other links to scan
     * @test
     */
    public function testScanNoOtherLinks()
    {
        $params = ['controller' => 'Pages', 'action' => 'display', 'nolinks'];
        $this->LinkScanner = $this->getLinkScannerClientGetsFromTests($params);

        //Enables event tracking
        $eventManager = $this->LinkScanner->getEventManager();
        $eventManager->setEventList(new EventList);

        $this->LinkScanner->scan();

        $this->assertEventNotFired(LINK_SCANNER . '.foundLinksToBeScanned', $eventManager);
    }

    /**
     * Test for `setFullBaseUrl()` method
     * @test
     */
    public function testSetFullBaseUrl()
    {
        foreach ([
            'http://newFullBaseUrl.com',
            'http://newFullBaseUrl.com/',
            'http://newFullBaseUrl.com/site',
            'https://newFullBaseUrl.com',
            'https://www.newFullBaseUrl.com',
        ] as $newFullBaseUrl) {
            $result = $this->LinkScanner->setFullBaseUrl($newFullBaseUrl);

            $expectedFullBaseUrl = $newFullBaseUrl;

            if (substr($expectedFullBaseUrl, -1) !== '/') {
                $expectedFullBaseUrl .= '/';
            }

            $this->assertInstanceof(LinkScanner::class, $result);
            $this->assertEquals($expectedFullBaseUrl, $this->LinkScanner->fullBaseUrl);
            $this->assertEquals('newFullBaseUrl.com', $this->LinkScanner->host);
        }
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
