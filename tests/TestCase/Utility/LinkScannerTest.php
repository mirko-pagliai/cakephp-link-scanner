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
use Cake\Event\EventList;
use Cake\TestSuite\IntegrationTestCase;
use LinkScanner\Utility\LinkScanner;
use Reflection\ReflectionTrait;
use Zend\Diactoros\Stream;

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
    }

    /**
     * Teardown any static object changes and restore them
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        foreach (glob(TMP . "results*") as $file) {
            unlink($file);
        }
    }

    /**
     * Internal method to get a `LinkScanner` instance with a stub for the
     *  `Client` instance.
     * @return LinkScanner
     */
    protected function getLinkScannerWithStubClient()
    {
        $this->LinkScanner = new LinkScanner('http://google.com');

        $this->LinkScanner->Client = $this->getMockBuilder(get_class($this->LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $this->LinkScanner->Client->method('get')
            ->will($this->returnCallback(function () {
                $request = unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_response'));
                $body = unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_body'));
                $stream = new Stream('php://memory', 'rw');
                $stream->write($body);
                $this->setProperty($request, 'stream', $stream);

                return $request;
            }));

        return $this->LinkScanner;
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

        foreach ([
            ['controller' => 'Pages', 'action' => 'display', 'nolinks'],
            '//localhost',
            'http://localhost',
            'https://localhost',
            'http://localhost/page',
            '//localhost/page',
            'https://localhost/page',
            'relative.html',
            '/relative.html',
        ] as $url) {
            $this->assertFalse($isExternalUrlMethod($url));
        }

        foreach ([
            'http://localhost.com',
            '//localhost.com',
            'http://subdomain.localhost',
            'http://www.google.com',
            'http://google.com',
            '//google.com',
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

        $this->LinkScanner->Client = $this->getMockBuilder(get_class($this->LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $this->LinkScanner->Client->method('get')
            ->will($this->returnCallback(function ($url) {
                $this->get($url);

                return $this->_response;
            }));

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

        $this->LinkScanner = $this->getLinkScannerWithStubClient();

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
        $this->setProperty($this->LinkScanner, 'externalLinks', ['value']);

        foreach (['currentDepth', 'endTime', 'startTime'] as $property) {
            $this->setProperty($this->LinkScanner, $property, 1);
        }

        $result = $this->LinkScanner->reset();
        $this->assertInstanceof(get_class($this->LinkScanner), $result);

        $ResultScan = $this->getProperty($this->LinkScanner, 'ResultScan');
        $this->assertInstanceof('LinkScanner\ResultScan', $ResultScan);
        $this->assertEmpty($ResultScan->toArray());

        $this->assertEquals([], $this->getProperty($this->LinkScanner, 'externalLinks'));

        foreach (['currentDepth', 'endTime', 'startTime'] as $property) {
            $this->assertEquals(0, $this->getProperty($this->LinkScanner, $property));
        }
    }

    /**
     * Test for `export()` method
     * @test
     */
    public function testExport()
    {
        $this->LinkScanner = $this->getLinkScannerWithStubClient();

        $this->LinkScanner->setMaxDepth(1)->scan();

        foreach (['array', 'html', 'xml'] as $format) {
            $result = $this->LinkScanner->export($format);

            $this->assertFileExists($result);
            $this->assertEquals(TMP, dirname($result) . DS);
            $this->assertRegexp(
                '/^results_google\.com_[\d\-]{10}\s[\d:]{8}(\.(html|xml))?$/',
                basename($result)
            );
        }

        //Tries with a custom filename
        $filename = TMP . 'results_custom_filename';
        $result = $this->LinkScanner->export('array', $filename);
        $this->assertFileExists($result);
        $this->assertEquals($result, $filename);
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $this->LinkScanner = $this->getLinkScannerWithStubClient();

        $result = $this->LinkScanner->setMaxDepth(1)->scan();

        $this->assertInstanceof('LinkScanner\Utility\LinkScanner', $result);

        $this->assertTrue(is_int($this->getProperty($this->LinkScanner, 'startTime')));
        $this->assertTrue(is_int($this->getProperty($this->LinkScanner, 'endTime')));

        $ResultScan = $this->getProperty($this->LinkScanner, 'ResultScan');
        $this->assertInstanceof('LinkScanner\ResultScan', $ResultScan);
        $this->assertCount(9, $ResultScan);

        foreach ($ResultScan->toArray() as $item) {
            $this->assertEquals(['code', 'external', 'type', 'url'], array_keys($item));
            $this->assertFalse($item['external']);
            $this->assertContains($item['code'], [200, 500]);
            $this->assertContains($item['type'], ['text/html; charset=ISO-8859-1']);
        }

        $this->assertCount(13, $this->getProperty($this->LinkScanner, 'externalLinks'));

        $this->LinkScanner = $this->getMockBuilder(get_class($this->LinkScanner))
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
        $this->LinkScanner = $this->getLinkScannerWithStubClient();

        //Enables event tracking
        $this->LinkScanner->getEventManager()->setEventList(new EventList);

        $result = $this->LinkScanner->setMaxDepth(1)->scan();

        $eventList = $this->LinkScanner->getEventManager()->getEventList();
        $this->assertEquals(21, $eventList->count());
        $this->assertEquals(LINK_SCANNER . '.scanStarted', $eventList[0]->getName());
        $this->assertEquals(LINK_SCANNER . '.scanCompleted', $eventList[20]->getName());

        foreach (range(1, 19) as $key) {
            $validEvents = [
                LINK_SCANNER . '.beforeScanUrl',
                LINK_SCANNER . '.afterScanUrl',
                LINK_SCANNER . '.foundLinksToBeScanned',
            ];

            $this->assertContains($eventList[$key]->getName(), $validEvents);
        }
    }

    /**
     * Test for `setMaxDepth()` method
     * @test
     */
    public function testSetMaxDepth()
    {
        $maxDepthProperty = function () {
            return $this->getProperty($this->LinkScanner, 'maxDepth');
        };

        $this->assertEquals(0, $maxDepthProperty());

        foreach ([0, 1] as $depth) {
            $result = $this->LinkScanner->setMaxDepth($depth);
            $this->assertInstanceof(get_class($this->LinkScanner), $result);
            $this->assertEquals($depth, $maxDepthProperty());
        }
    }

    /**
     * Test for `setTimeout()` method
     * @test
     */
    public function testSetTimeout()
    {
        $timeoutProperty = function () {
            return $this->getProperty($this->LinkScanner, 'timeout');
        };

        $this->assertEquals(30, $timeoutProperty());

        foreach ([0, 1] as $timeout) {
            $result = $this->LinkScanner->setTimeout($timeout);
            $this->assertInstanceof(get_class($this->LinkScanner), $result);
            $this->assertEquals($timeout, $timeoutProperty());
        }
    }
}
