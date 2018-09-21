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
namespace LinkScanner\Test\TestCase\Shell;

use Cake\Cache\Cache;
use Cake\Console\ConsoleIo;
use Cake\TestSuite\ConsoleIntegrationTestCase;
use Cake\TestSuite\Stub\ConsoleOutput;
use LinkScanner\Shell\LinkScannerShell;
use LinkScanner\TestSuite\TestCaseTrait;
use LinkScanner\Utility\LinkScanner;

/**
 * LinkScannerShellTest class
 */
class LinkScannerShellTest extends ConsoleIntegrationTestCase
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
     * @var \LinkScanner\Shell\LinkScannerShell;
     */
    protected $LinkScannerShell;

    /**
     * @var \Cake\TestSuite\Stub\ConsoleOutput
     */
    protected $err;

    /**
     * @var string|array|null
     */
    protected $fullBaseUrl;

    /**
     * @var \Cake\TestSuite\Stub\ConsoleOutput
     */
    protected $out;

    /**
     * Internal method to set and get the `LinkScannerShell` object and all
     *  properties of this test class
     * @return LinkScannerShell
     */
    protected function getLinkScannerShell()
    {
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl, null, $this->getClientReturnsSampleResponse());
        $this->EventManager = $this->getEventManager($this->LinkScanner);

        $this->out = new ConsoleOutput;
        $this->err = new ConsoleOutput;
        $io = new ConsoleIo($this->out, $this->err);
        $io->level(2);

        $this->LinkScannerShell = $this->getMockBuilder(LinkScannerShell::class)
            ->setConstructorArgs([$io])
            ->setMethods(['in', '_stop'])
            ->getMock();

        $this->LinkScannerShell->LinkScanner = $this->LinkScanner;

        return $this->LinkScannerShell;
    }

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

        $this->fullBaseUrl = 'http://google.com';

        $this->getLinkScannerShell();
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
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $this->LinkScannerShell->params['maxDepth'] = 1;
        $this->LinkScannerShell->scan();

        $messages = $this->out->messages();
        $this->assertCount(5, $messages);

        $this->assertTextStartsWith(sprintf('<info>Scan started for %s', $this->fullBaseUrl), current($messages));
        $this->assertRegexp('/at [\d\-]+\s[\d\:]+<\/info>$/', current($messages));
        $this->assertEquals(sprintf('Checking %s ...', $this->fullBaseUrl), next($messages));
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertTextStartsWith('Elapsed time: ', next($messages));
        $this->assertRegexp('/\d+ seconds?$/', current($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));

        $this->assertEmpty($this->err->messages());

        $this->assertEventFired(LINK_SCANNER . '.' . 'scanStarted', $this->EventManager);
        $this->assertEventFired(LINK_SCANNER . '.' . 'scanCompleted', $this->EventManager);
        $this->assertEventFired(LINK_SCANNER . '.' . 'beforeScanUrl', $this->EventManager);
        $this->assertEventFired(LINK_SCANNER . '.' . 'afterScanUrl', $this->EventManager);
        $this->assertEventNotFired(LINK_SCANNER . '.foundLinkToBeScanned', $this->EventManager);
        $this->assertEventNotFired(LINK_SCANNER . '.resultsExported', $this->EventManager);
    }

    /**
     * Test for `scan()` method, with cache enabled
     * @test
     */
    public function testScanCacheEnabled()
    {
        Cache::enable();

        $this->LinkScannerShell->params['verbose'] = true;
        $this->LinkScannerShell->scan();

        $this->assertContains(sprintf(
            '<success>The cache is enabled and its duration is `%s`</success>',
            Cache::getConfig(LINK_SCANNER)['duration']
        ), $this->out->messages());
    }

    /**
     * Test for `scan()` method, with an invalid url
     * @expectedException Cake\Console\Exception\StopException
     * @expectedExceptionMessage Invalid url `invalid`
     * @test
     */
    public function testScanInvalidUrl()
    {
        $this->LinkScannerShell->params['fullBaseUrl'] = 'invalid';
        $this->LinkScannerShell->scan();
    }

    /**
     * Test for `scan()` method, with some parameters
     * @test
     */
    public function testScanParams()
    {
        touch(LINK_SCANNER_LOCK_FILE);
        $params = [
            'export' => null,
            'force' => true,
            'fullBaseUrl' => 'http://anotherFullBaseUrl',
            'maxDepth' => 3,
            'timeout' => 15,
        ];
        $this->LinkScannerShell->params = $params;
        $this->LinkScannerShell->scan();
        $this->assertFileNotExists(LINK_SCANNER_LOCK_FILE);

        $this->assertEventFired(LINK_SCANNER . '.resultsExported', $this->EventManager);

        $expectedExportFile = LINK_SCANNER_TARGET . DS . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime;
        $this->assertFileExists($expectedExportFile);

        $messages = $this->out->messages();
        $this->assertTextContains(sprintf('Scan started for %s', $this->LinkScanner->fullBaseUrl), current($messages));
        $this->assertRegexp('/at [\d\-:\s]+<\/info>$/', current($messages));
        $this->assertTextContains(sprintf('Results have been exported to', $expectedExportFile), end($messages));

        $this->assertEmpty($this->err->messages());

        $this->assertEquals($params['fullBaseUrl'], $this->LinkScanner->fullBaseUrl);
        $this->assertEquals($params['maxDepth'], $this->LinkScanner->getConfig('maxDepth'));
        $this->assertEquals($params['timeout'], $this->LinkScanner->Client->getConfig('timeout'));

        foreach ([
            'example' => LINK_SCANNER_TARGET . DS . 'example',
            TMP . 'example' => TMP . 'example',
        ] as $filename => $expectedExportFile) {
            $this->LinkScannerShell = $this->getLinkScannerShell();
            $this->LinkScannerShell->params = ['export' => $filename] + $params;
            $this->LinkScannerShell->scan();

            $this->assertEventFired(LINK_SCANNER . '.resultsExported', $this->EventManager);

            $this->assertFileExists($expectedExportFile);

            $messages = $this->out->messages();
            $this->assertTextContains(sprintf('Results have been exported to', $expectedExportFile), end($messages));
        }
    }

    /**
     * Test for `scan()` method, with verbose
     * @test
     */
    public function testScanVerbose()
    {
        $this->LinkScannerShell->params['verbose'] = true;
        $this->LinkScannerShell->scan();

        $messages = $this->out->messages();
        $count = count($messages);

        //First five lines
        $this->assertRegexp('/^\-{63}$/', current($messages));
        $this->assertTextStartsWith(sprintf('<info>Scan started for %s', $this->fullBaseUrl), next($messages));
        $this->assertRegexp(sprintf('/t [\d\-]+\s[\d\:]+<\/info>$/'), current($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));
        $this->assertTextContains('The cache is disabled', next($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));

        //Last lines
        while (key($messages) !== $count - 5) {
            next($messages);
        };
        $this->assertRegexp('/^\-{63}$/', current($messages));
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertTextStartsWith('Elapsed time: ', next($messages));
        $this->assertRegexp('/\d+ seconds?$/', current($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));

        //Removes already checked lines
        $messages = array_slice($messages, 5, -5);

        //Checks intermediate lines. It chunks them into groups of three
        $i = 0;
        $chunkMessages = array_chunk($messages, 3);
        foreach ($chunkMessages as $messages) {
            $this->assertRegExp('/^Checking .+ \.{3}$/', $messages[0]);
            $this->assertEquals('<success>OK</success>', $messages[1]);

            //Not for last line
            if ($i !== count($chunkMessages) - 1) {
                $this->assertRegexp('/^Link found: .+/', $messages[2]);
            }
            $i++;
        }
    }

    /**
     * Test for `scan()` method, with an error response (404 status code)
     * @test
     */
    public function testScanWithErrorResponse()
    {
        $this->LinkScannerShell->LinkScanner = new LinkScanner(null, null, $this->getClientReturnsErrorResponse());
        $this->LinkScannerShell->params['verbose'] = true;
        $this->LinkScannerShell->scan();

        $this->assertEquals(['<warning>404</warning>'], $this->err->messages());
    }

    /**
     * Test for `getOptionParser()` method
     * @test
     */
    public function testGetOptionParser()
    {
        $parser = $this->LinkScannerShell->getOptionParser();

        $this->assertInstanceOf('Cake\Console\ConsoleOptionParser', $parser);
        $this->assertArrayKeysEqual(['scan'], $parser->subcommands());
        $this->assertEquals('Shell to perform links scanner', $parser->getDescription());
        $this->assertArrayKeysEqual(['help', 'quiet', 'verbose'], $parser->options());

        //Tests options
        $this->assertArrayKeysEqual([
            'export',
            'force',
            'fullBaseUrl',
            'help',
            'maxDepth',
            'quiet',
            'timeout',
            'verbose',
        ], $parser->subcommands()['scan']->parser()->options());
    }
}
