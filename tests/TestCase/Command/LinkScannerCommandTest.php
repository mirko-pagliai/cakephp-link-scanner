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
use Cake\Console\Exception\StopException;
use Cake\TestSuite\Stub\ConsoleOutput;
use LinkScanner\Command\LinkScannerCommand;
use LinkScanner\TestSuite\IntegrationTestTrait;
use LinkScanner\TestSuite\TestCase;
use LinkScanner\Utility\LinkScanner;
use MeTools\TestSuite\ConsoleIntegrationTestTrait;

/**
 * LinkScannerCommandTest class
 */
class LinkScannerCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use IntegrationTestTrait;

    /**
     * @var \LinkScanner\Utility\LinkScanner
     */
    protected $LinkScanner;

    /**
     * @var string
     */
    protected $fullBaseUrl = 'http://google.com';

    /**
     * Internal method to set and get the `LinkScannerCommand` instance and all
     *  properties of this test class
     */
    protected function setLinkScannerCommand()
    {
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl, $this->getClientReturnsSampleResponse());
        $this->getEventManager();

        $this->_out = new ConsoleOutput;
        $this->_err = new ConsoleOutput;
        $this->io = new ConsoleIo($this->_out, $this->_err);
        $this->io->level(2);

        $this->Command = new LinkScannerCommand;
        $this->Command->LinkScanner = $this->LinkScanner;
    }

    /**
     * Setup the test case, backup the static object values so they can be
     * restored. Specifically backs up the contents of Configure and paths in
     *  App if they have not already been backed up
     * @return void
     */
    public function setUp()
    {
        $this->setLinkScannerCommand();

        parent::setUp();
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $this->Command->run(['--max-depth=1'], $this->io);

        $this->assertEquals([
            'cache' => true,
            'excludeLinks' => ['\{.*\}', 'javascript:'],
            'externalLinks' => true,
            'followRedirects' => false,
            'maxDepth' => 1,
            'lockFile' => true,
            'target' => TMP,
        ], $this->LinkScanner->getConfig());

        $this->assertOutputRegExp(sprintf('/Scan started for %s at [\d\-]+\s[\d\:]+/', preg_quote($this->fullBaseUrl, '/')));
        $this->assertOutputContains(sprintf('Checking %s ...', $this->fullBaseUrl));
        $this->assertOutputRegExp('/Scan completed at [\d\-]+\s[\d\:]+/');
        $this->assertOutputRegExp('/Elapsed time: \d+ seconds?/');
        $this->assertOutputRegExp('/Total scanned links: \d+/');
        $this->assertErrorEmpty();

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

        //With an error response (404 status code)
        $this->Command->LinkScanner = new LinkScanner($this->fullBaseUrl, $this->getClientReturnsErrorResponse());
        $this->Command->run(['--verbose'], $this->io);
        $this->assertErrorContains('404');
    }
    /**
     * Test for `scan()` method, with cache enabled
     * @test
     */
    public function testScanCacheEnabled()
    {
        $this->Command->run(['--verbose'], $this->io);
        $expectedDuration = Cache::getConfig('LinkScanner')['duration'];
        $this->assertOutputContains(sprintf('The cache is enabled and its duration is `%s`', $expectedDuration));
    }

    /**
     * Test for `scan()` method, with some parameters
     * @test
     */
    public function testScanParams()
    {
        touch(LINK_SCANNER_LOCK_FILE);
        $params = [
            '--export',
            '--force',
            '--max-depth=2',
            '--timeout=15',
            '--verbose',
        ];
        $this->Command->run($params, $this->io);

        $expectedConfig = [
            'cache' => true,
            'excludeLinks' => ['\{.*\}', 'javascript:'],
            'externalLinks' => true,
            'followRedirects' => false,
            'maxDepth' => 2,
            'lockFile' => false,
            'target' => TMP,
        ];
        $expectedDuration = Cache::getConfig('LinkScanner')['duration'];
        $expectedFilename = $this->LinkScanner->getConfig('target') . DS . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime;

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertEquals(15, $this->LinkScanner->Client->getConfig('timeout'));
        $this->assertFileNotExists(LINK_SCANNER_LOCK_FILE);
        $this->assertFileExists($expectedFilename);
        $this->assertEventFired('LinkScanner.resultsExported', $this->LinkScanner->getEventManager());
        $this->assertOutputRegExp(sprintf('/Scan started for %s/', preg_quote($this->fullBaseUrl, '/')));
        $this->assertOutputContains(sprintf('The cache is enabled and its duration is `%s`', $expectedDuration));
        $this->assertOutputContains('Force mode is enabled');
        $this->assertOutputContains('Scanning of external links is enabled');
        $this->assertOutputContains('Redirects will not be followed');
        $this->assertOutputContains('Maximum depth of the scan: 2');
        $this->assertOutputContains('Timeout in seconds for GET requests: 15');
        $this->assertOutputContains(sprintf('Results have been exported to', $expectedFilename));
        $this->assertErrorEmpty();

        //With disabled cache
        $params[] = '--no-cache';
        $this->Command->run($params, $this->io);
        $this->assertOutputContains('The cache is disabled');
        $this->assertEquals(['cache' => false] + $expectedConfig, $this->LinkScanner->getConfig());

        //With a different full base url
        array_pop($params);
        $params[] = '--full-base-url=http://anotherFullBaseUrl';
        $this->setLinkScannerCommand();
        $this->Command->run($params, $this->io);
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertEquals('http://anotherFullBaseUrl', $this->LinkScanner->fullBaseUrl);
        $this->assertOutputRegExp(sprintf('/Scan started for %s/', preg_quote($this->LinkScanner->fullBaseUrl, '/')));
        $this->assertErrorEmpty();

        //Disables external links
        array_pop($params);
        $params = array_merge($params, ['--full-base-url=' . $this->fullBaseUrl, '--disable-external-links']);

        $this->setLinkScannerCommand();
        $this->Command->run($params, $this->io);
        $this->assertEquals(['externalLinks' => false] + $expectedConfig, $this->LinkScanner->getConfig());

        $lineDifferentFullBaseUrl = function ($line) {
            $pattern = sprintf('/^Checking https?:\/\/%s/', preg_quote(get_hostname_from_url($this->fullBaseUrl)));

            return substr($line, 0, strlen('Checking')) === 'Checking' && !preg_match($pattern, $line);
        };
        $this->assertEmpty(array_filter($this->_out->messages(), $lineDifferentFullBaseUrl));
        $this->assertOutputContains(('Scanning of external links is not enabled'));

        //Re-enables external links
        array_pop($params);
        $this->setLinkScannerCommand();
        $this->Command->run($params, $this->io);
        $expectedConfig['externalLinks'] = true;
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertNotEmpty(array_filter($this->_out->messages(), $lineDifferentFullBaseUrl));

        foreach ([
            'example' => $this->LinkScanner->getConfig('target') . DS . 'example',
            TMP . 'example' => TMP . 'example',
        ] as $filename => $expectedExportFile) {
            $this->setLinkScannerCommand();
            array_shift($params);
            array_unshift($params, '--export-with-filename=' . $filename);

            $this->Command->run($params, $this->io);
            $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
            $this->assertFileExists($expectedExportFile);
            $this->assertEventFired('LinkScanner.resultsExported', $this->LinkScanner->getEventManager());
            $this->assertOutputContains(sprintf('Results have been exported to', $expectedExportFile));
            $this->assertErrorEmpty();
        }

        //Enables follow redirects
        $this->LinkScanner = $this->getLinkScannerClientReturnsFromTests();
        $this->Command->LinkScanner = $this->LinkScanner;
        $this->getEventManager();
        array_pop($params);
        $this->Command->run(array_merge($params, ['--follow-redirects']), $this->io);
        $this->assertEventFired('LinkScanner.foundRedirect', $this->LinkScanner->getEventManager());
        $this->assertContains(['followRedirects' => true], $this->LinkScanner->getConfig());
        $this->assertOutputContains('Redirects will be followed');
        $this->assertOutputContains('Redirect found: http://localhost/pages/third_page');

        //With an invalid full base url
        $this->expectException(StopException::class);
        $this->Command->run(['--full-base-url=invalid'], $this->io);
    }

    /**
     * Test for `scan()` method, with verbose
     * @test
     */
    public function testScanVerbose()
    {
        $this->Command->run(['--no-cache', '--verbose'], $this->io);
        $this->assertOutputRegExp(sprintf('/Scan started for %s at [\d\-]+\s[\d\:]+/', preg_quote($this->fullBaseUrl, '/')));
        $this->assertOutputContains('The cache is disabled');
        $this->assertOutputContains('Force mode is not enabled');
        $this->assertOutputContains('Scanning of external links is enabled');
        $this->assertOutputContains('Redirects will not be followed');
        $this->assertOutputRegExp('/Timeout in seconds for GET requests: \d+/');

        //Moves to final lines
        $messages = $this->_out->messages();
        $count = count($messages);
        while (key($messages) !== $count - 5) {
            next($messages);
        };

        $this->assertRegexp('/^\-+$/', current($messages));
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertRegexp('/^Elapsed time: \d+ seconds?$/', next($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));
        $this->assertRegexp('/^\-+$/', next($messages));

        //Removes already checked lines and checks intermediate lines
        foreach (array_slice($messages, 9, -5) as $message) {
            $this->assertRegExp('/^(<success>OK<\/success>|Checking .+ \.{3}|Link found: .+)$/', $message);
        }
    }

    /**
     * Test for `buildOptionParser()` method
     * @test
     */
    public function testBuildOptionParser()
    {
        $parser = $this->invokeMethod($this->Command, 'buildOptionParser', [new ConsoleOptionParser]);
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
            'no-cache',
            'quiet',
            'timeout',
            'verbose',
        ], $parser->options());
    }
}
