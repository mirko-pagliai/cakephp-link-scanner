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
namespace LinkScanner\Test\TestCase\Command;

use Cake\Cache\Cache;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\Stub\ConsoleOutput;
use LinkScanner\Command\LinkScannerCommand;
use LinkScanner\TestSuite\TestCaseTrait;
use LinkScanner\Utility\LinkScanner;
use MeTools\TestSuite\TestCase;

/**
 * LinkScannerCommandTest class
 */
class LinkScannerCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use TestCaseTrait;

    /**
     * @var \LinkScanner\Utility\LinkScanner
     */
    protected $LinkScanner;

    /**
     * @var \LinkScanner\Command\LinkScannerCommand;
     */
    protected $LinkScannerCommand;

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
     * Internal method to set and get the `LinkScannerCommand` instance and all
     *  properties of this test class
     */
    protected function setLinkScannerCommand()
    {
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl);
        $this->LinkScanner->Client = $this->getClientReturnsSampleResponse();

        $this->getEventManager();

        $this->out = new ConsoleOutput;
        $this->err = new ConsoleOutput;
        $this->io = new ConsoleIo($this->out, $this->err);
        $this->io->level(2);

        $this->LinkScannerCommand = new LinkScannerCommand;
        $this->LinkScannerCommand->LinkScanner = $this->LinkScanner;
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

        safe_unlink('LinkScanner');

        $this->fullBaseUrl = 'http://google.com';
        $this->setLinkScannerCommand();
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
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $this->LinkScannerCommand->run(['--max-depth=1'], $this->io);

        $this->assertEquals([
            'excludeLinks' => ['\{.*\}', 'javascript:'],
            'externalLinks' => true,
            'followRedirects' => false,
            'maxDepth' => 1,
        ], $this->LinkScanner->getConfig());

        $messages = $this->out->messages();
        $this->assertTextStartsWith(sprintf('<info>Scan started for %s', $this->fullBaseUrl), current($messages));
        $this->assertRegexp('/at [\d\-]+\s[\d\:]+<\/info>$/', current($messages));
        $this->assertEquals(sprintf('Checking %s ...', $this->fullBaseUrl), next($messages));
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertTextStartsWith('Elapsed time: ', next($messages));
        $this->assertRegexp('/\d+ seconds?$/', current($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));

        $this->assertEmpty($this->err->messages());

        foreach ([
            'afterScanUrl',
            'beforeScanUrl',
            'scanStarted',
            'scanCompleted',
        ] as $eventName) {
            $this->assertEventFired('LinkScanner.' . $eventName, $this->LinkScanner->getEventManager());
        }

        foreach ([
            'foundLinkToBeScanned',
            'foundRedirect',
            'resultsExported',
        ] as $eventName) {
            $this->assertEventNotFired('LinkScanner.' . $eventName, $this->LinkScanner->getEventManager());
        }
    }

    /**
     * Test for `scan()` method, with cache enabled
     * @test
     */
    public function testScanCacheEnabled()
    {
        Cache::setConfig('LinkScanner', [
            'className' => 'File',
            'duration' => '+1 day',
            'path' => CACHE,
            'prefix' => 'link_scanner_',
        ]);

        $this->LinkScannerCommand->run(['--verbose'], $this->io);

        $this->assertContains(sprintf(
            '<success>The cache is enabled and its duration is `%s`</success>',
            Cache::getConfig('LinkScanner')['duration']
        ), $this->out->messages());

        Cache::clearAll();
        Cache::drop('LinkScanner');
    }

    /**
     * Test for `scan()` method, with an invalid url
     * @expectedException Cake\Console\Exception\StopException
     * @test
     */
    public function testScanInvalidUrl()
    {
        $this->LinkScannerCommand->run(['--full-base-url=invalid'], $this->io);
    }

    /**
     * Test for `scan()` method, with some parameters
     * @test
     */
    public function testScanParams()
    {
        touch(LINK_SCANNER_LOCK_FILE);
        $expectedConfig = [
            'excludeLinks' => ['\{.*\}', 'javascript:'],
            'externalLinks' => true,
            'followRedirects' => false,
            'maxDepth' => 2,
        ];
        $params = [
            '--export',
            '--force',
            '--max-depth=2',
            '--timeout=15',
            '--verbose',
        ];
        $this->LinkScannerCommand->run($params, $this->io);

        $expectedFilename = LINK_SCANNER_TARGET . DS . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime;

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertEquals(15, $this->LinkScanner->Client->getConfig('timeout'));

        $this->assertFileNotExists(LINK_SCANNER_LOCK_FILE);
        $this->assertFileExists($expectedFilename);
        $this->assertEventFired('LinkScanner.resultsExported', $this->LinkScanner->getEventManager());

        $messages = $this->out->messages();
        $this->assertRegexp('/^\-+$/', current($messages));
        $this->assertTextContains(sprintf('Scan started for %s', $this->fullBaseUrl), next($messages));
        $this->assertRegexp('/at [\d\-:\s]+<\/info>$/', current($messages));
        $this->assertRegexp('/^\-+$/', next($messages));
        $this->assertTextContains('The cache is disabled', next($messages));
        $this->assertTextContains('Force mode is enabled', next($messages));
        $this->assertTextContains('Scanning of external links is enabled', next($messages));
        $this->assertTextContains('Redirects will not be followed', next($messages));
        $this->assertTextContains('Maximum depth of the scan: 2', next($messages));
        $this->assertTextContains('Timeout in seconds for GET requests: 15', next($messages));
        $this->assertRegexp('/^\-+$/', next($messages));
        $this->assertTextContains(sprintf('Results have been exported to', $expectedFilename), end($messages));

        $this->assertEmpty($this->err->messages());

        //Changes the full base url
        $params[] = '--full-base-url=http://anotherFullBaseUrl';
        $this->setLinkScannerCommand();
        $this->LinkScannerCommand->run($params, $this->io);

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertEquals('http://anotherFullBaseUrl', $this->LinkScanner->fullBaseUrl);

        $this->assertNotEmpty(preg_grep(sprintf('/Scan started for %s/', preg_quote($this->LinkScanner->fullBaseUrl, '/')), $this->out->messages()));
        $this->assertEmpty($this->err->messages());

        //Disables external links
        array_pop($params);
        $params = array_merge($params, ['--full-base-url=' . $this->fullBaseUrl, '--disable-external-links']);

        $this->setLinkScannerCommand();
        $this->LinkScannerCommand->run($params, $this->io);

        $this->assertEquals(['externalLinks' => false] + $expectedConfig, $this->LinkScanner->getConfig());

        $lineDifferentFullBaseUrl = function ($line) {
            $pattern = sprintf('/^Checking https?:\/\/%s/', preg_quote(get_hostname_from_url($this->fullBaseUrl)));

            return substr($line, 0, strlen('Checking')) === 'Checking' && !preg_match($pattern, $line);
        };
        $this->assertEmpty(array_filter($this->out->messages(), $lineDifferentFullBaseUrl));
        $this->assertNotEmpty(preg_grep('/Scanning of external links is not enabled/', $this->out->messages()));

        //Re-enables external links
        array_pop($params);
        $this->setLinkScannerCommand();
        $this->LinkScannerCommand->run($params, $this->io);

        $expectedConfig['externalLinks'] = true;
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

        $this->assertNotEmpty(array_filter($this->out->messages(), $lineDifferentFullBaseUrl));

        foreach ([
            'example' => LINK_SCANNER_TARGET . DS . 'example',
            TMP . 'example' => TMP . 'example',
        ] as $filename => $expectedExportFile) {
            $this->setLinkScannerCommand();
            array_shift($params);
            array_unshift($params, '--export-with-filename=' . $filename);
            $this->LinkScannerCommand->run($params, $this->io);

            $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

            $this->assertFileExists($expectedExportFile);
            $this->assertEventFired('LinkScanner.resultsExported', $this->LinkScanner->getEventManager());

            $messages = $this->out->messages();
            $this->assertTextContains(sprintf('Results have been exported to', $expectedExportFile), end($messages));

            $this->assertEmpty($this->err->messages());
        }

        //Enables follow redirects
        $this->setLinkScannerCommand();
        $this->LinkScannerCommand->run(array_merge($params, ['--follow-redirects']), $this->io);

        $expectedConfig = ['followRedirects' => true] + $expectedConfig;
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

        $this->assertNotEmpty(preg_grep('/Redirects will be followed/', $this->out->messages()));
    }

    /**
     * Test for `scan()` method, with verbose
     * @test
     */
    public function testScanVerbose()
    {
        $this->LinkScannerCommand->run(['--verbose'], $this->io);

        $messages = $this->out->messages();
        $count = count($messages);

        //Initial lines
        $this->assertRegexp('/^\-+$/', current($messages));
        $this->assertTextStartsWith(sprintf('<info>Scan started for %s', $this->fullBaseUrl), next($messages));
        $this->assertRegexp(sprintf('/t [\d\-]+\s[\d\:]+<\/info>$/'), current($messages));
        $this->assertRegexp('/^\-+$/', next($messages));
        $this->assertTextContains('The cache is disabled', next($messages));
        $this->assertTextContains('Force mode is not enabled', next($messages));
        $this->assertTextContains('Scanning of external links is enabled', next($messages));
        $this->assertTextContains('Redirects will not be followed', next($messages));
        $this->assertRegexp('/Timeout in seconds for GET requests: \d+/', next($messages));
        $this->assertRegexp('/^\-+$/', next($messages));

        //Final lines
        while (key($messages) !== $count - 5) {
            next($messages);
        };

        $this->assertRegexp('/^\-+$/', current($messages));
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertTextStartsWith('Elapsed time: ', next($messages));
        $this->assertRegexp('/\d+ seconds?$/', current($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));
        $this->assertRegexp('/^\-+$/', next($messages));

        //Removes already checked lines and checks intermediate lines
        foreach (array_slice($messages, 9, -5) as $message) {
            $this->assertRegExp('/^(<success>OK<\/success>|Checking .+ \.{3}|Link found: .+)$/', $message);
        }
    }

    /**
     * Test for `scan()` method, with an error response (404 status code)
     * @test
     */
    public function testScanWithErrorResponse()
    {
        $this->LinkScannerCommand->LinkScanner->Client = $this->getClientReturnsErrorResponse();
        $this->LinkScannerCommand->run(['--verbose'], $this->io);
        $this->assertEquals(['404'], $this->err->messages());
    }

    /**
     * Test for `buildOptionParser()` method
     * @test
     */
    public function testBuildOptionParser()
    {
        $parser = $this->invokeMethod($this->LinkScannerCommand, 'buildOptionParser', [new ConsoleOptionParser]);
        $this->assertInstanceOf(ConsoleOptionParser::class, $parser);
        $this->assertEquals('Performs a complete scan', $parser->getDescription());
        $this->assertEmpty($parser->arguments());
        $this->assertArrayKeysEqual([
            'disable-external-links',
            'export',
            'export-with-filename',
            'follow-redirects',
            'force',
            'full-base-url',
            'help',
            'max-depth',
            'quiet',
            'timeout',
            'verbose',
        ], $parser->options());
    }
}
