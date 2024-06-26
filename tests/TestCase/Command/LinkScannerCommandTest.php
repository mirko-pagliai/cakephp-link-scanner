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
namespace LinkScanner\Test\TestCase\Command;

use Cake\Cache\Cache;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\Exception\StopException;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Console\TestSuite\StubConsoleOutput;
use LinkScanner\Command\LinkScannerCommand;
use LinkScanner\TestSuite\TestCase;
use LinkScanner\Utility\LinkScanner;
use PHPUnit\Framework\Exception;
use Tools\TestSuite\ReflectionTrait;

/**
 * LinkScannerCommandTest class
 * @property \LinkScanner\Command\LinkScannerCommand $Command
 */
class LinkScannerCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use ReflectionTrait;

    /**
     * @var \LinkScanner\Command\LinkScannerCommand
     */
    protected LinkScannerCommand $Command;

    /**
     * @var \LinkScanner\Utility\LinkScanner|(\LinkScanner\Utility\LinkScanner&\PHPUnit\Framework\MockObject\MockObject)
     */
    protected LinkScanner $LinkScanner;

    /**
     * @var string
     */
    protected string $fullBaseUrl = 'https://google.com';

    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected ConsoleIo $_io;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->LinkScanner = new LinkScanner($this->getClientReturnsSampleResponse());
        $this->LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl);
        $this->getEventManager();

        $this->_out = new StubConsoleOutput();
        $this->_err = new StubConsoleOutput();
        $this->_io = new ConsoleIo($this->_out, $this->_err);
        $this->_io->level(2);

        $this->Command = new LinkScannerCommand();
        $this->Command->LinkScanner = $this->LinkScanner;
    }

    /**
     * @test
     * @uses \LinkScanner\Command\LinkScannerCommand::execute()
     */
    public function testExecute(): void
    {
        $expectedConfig = ['maxDepth' => 1] + $this->LinkScanner->getConfig();
        $this->Command->run(['--max-depth=1'], $this->_io);

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

        $this->assertOutputRegExp(sprintf('/Scan started for %s at [\d\-]+\s[\d\:]+/', preg_quote($this->fullBaseUrl, '/')));
        $this->assertOutputContains(sprintf('Checking %s ...', $this->fullBaseUrl));
        $this->assertOutputRegExp('/Scan completed at [\d\-]+\s[\d\:]+/');
        $this->assertOutputRegExp('/Elapsed time: \d+ seconds?/');
        $this->assertOutputRegExp('/Total scanned links\: [1-9]\d*/');
        $this->assertOutputContains('Invalid links: 0');
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
        $this->Command->LinkScanner = new LinkScanner($this->getClientReturnsErrorResponse());
        $this->Command->LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl);
        $this->Command->run(['--verbose'], $this->_io);
        $this->assertErrorContains('404');

        //Does not suppress PHPUnit exceptions, which are thrown anyway
        $this->expectException(Exception::class);
        $Client = $this->getClientStub();
        $Client->method('get')->willThrowException(new Exception());
        $this->Command->LinkScanner = new LinkScanner($Client);
        $this->Command->run(['--verbose'], $this->_io);
    }

    /**
     * @test
     * @uses \LinkScanner\Command\LinkScannerCommand::execute()
     */
    public function testExecuteCacheEnabled(): void
    {
        $this->Command->run(['--verbose'], $this->_io);
        $expectedDuration = Cache::getConfig('LinkScanner')['duration'];
        $this->assertOutputContains(sprintf('The cache is enabled and its duration is `%s`', $expectedDuration));
    }

    /**
     * @test
     * @uses \LinkScanner\Command\LinkScannerCommand::execute()
     */
    public function testExecuteWithSomeParameters(): void
    {
        touch($this->Command->LinkScanner->lockFile);
        $params = [
            '--export',
            '--force',
            '--max-depth=2',
            '--timeout=15',
            '--verbose',
        ];
        $expectedConfig = ['maxDepth' => 2, 'lockFile' => false] + $this->LinkScanner->getConfig();
        $this->Command->run($params, $this->_io);

        $expectedDuration = Cache::getConfig('LinkScanner')['duration'];
        $expectedFilename = $this->LinkScanner->getConfig('target') . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime;

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertEquals(15, $this->LinkScanner->Client->getConfig('timeout'));
        $this->assertFileDoesNotExist($this->LinkScanner->lockFile);
        $this->assertFileExists($expectedFilename);
        $this->assertEventFired('LinkScanner.resultsExported', $this->LinkScanner->getEventManager());
        $this->assertOutputRegExp(sprintf('/Scan started for %s/', preg_quote($this->fullBaseUrl, '/')));
        $this->assertOutputRegExp('/Total scanned links\: [1-9]\d*/');
        $this->assertOutputContains('Invalid links: 0');
        $this->assertOutputContains(sprintf('The cache is enabled and its duration is `%s`', $expectedDuration));
        $this->assertOutputContains('Force mode is enabled');
        $this->assertOutputContains('Scanning of external links is enabled');
        $this->assertOutputContains('Redirects will not be followed');
        $this->assertOutputContains('Maximum depth of the scan: 2');
        $this->assertOutputContains('Timeout in seconds for GET requests: 15');
        $this->assertOutputContains('Results have been exported to ' . $expectedFilename);
        $this->assertErrorEmpty();

        //With disabled cache
        $params[] = '--no-cache';
        $this->Command->run($params, $this->_io);
        $this->assertOutputContains('The cache is disabled');
        $this->assertEquals(['cache' => false] + $expectedConfig, $this->LinkScanner->getConfig());

        //With a different full base url
        array_pop($params);
        $params[] = '--full-base-url=http://anotherFullBaseUrl';
        self::setUp();
        $this->Command->run($params, $this->_io);
        $expectedConfig['fullBaseUrl'] = 'http://anotherFullBaseUrl';
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertOutputRegExp(sprintf('/Scan started for %s/', preg_quote($this->LinkScanner->getConfig('fullBaseUrl'), '/')));
        $this->assertOutputRegExp('/Total scanned links\: [1-9]\d*/');
        $this->assertOutputContains('Invalid links: 0');
        $this->assertErrorEmpty();

        //Exports only bad results.
        //It also works without the `--export` parameter
        self::setUp();
        $this->Command->run(array_merge(['--export-only-bad-results'] + $params), $this->_io);
        $expectedFilename = $this->LinkScanner->getConfig('target') . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime;
        $this->assertEquals(['exportOnlyBadResults' => true] + $expectedConfig, $this->LinkScanner->getConfig());
        $this->assertOutputRegExp(sprintf('/Scan started for %s/', preg_quote($this->LinkScanner->getConfig('fullBaseUrl'), '/')));
        $this->assertOutputRegExp('/Total scanned links\: [1-9]\d*/');
        $this->assertOutputContains('Invalid links: 0');

        $this->assertFileExists($expectedFilename);
        $this->assertEventFired('LinkScanner.resultsExported', $this->LinkScanner->getEventManager());
        $this->assertOutputContains('Results have been exported to ' . $expectedFilename);

        //Disables external links
        array_pop($params);
        $params = array_merge($params, ['--full-base-url=' . $this->fullBaseUrl, '--no-external-links']);

        self::setUp();
        $this->Command->run($params, $this->_io);
        $expectedConfig['externalLinks'] = false;
        $expectedConfig['fullBaseUrl'] = $this->fullBaseUrl;
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

        $differentLines = fn(string $line): bool =>
            str_starts_with($line, 'Checking') && !preg_match('/^Checking https?:\/\/google\.com/', $line);
        $this->assertEmpty(array_filter($this->_out->messages(), $differentLines));
        $this->assertOutputContains('Scanning of external links is not enabled');

        //Re-enables external links
        array_pop($params);
        self::setUp();
        $this->Command->run($params, $this->_io);
        $expectedConfig['externalLinks'] = true;
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertNotEmpty(array_filter($this->_out->messages(), $differentLines));

        foreach ([
            'example' => $this->LinkScanner->getConfig('target') . 'example',
            TMP . 'example' => TMP . 'example',
        ] as $filename => $expectedExportFile) {
            self::setUp();
            array_shift($params);
            array_unshift($params, '--export-with-filename=' . $filename);

            $this->Command->run($params, $this->_io);
            $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
            $this->assertFileExists($expectedExportFile);
            $this->assertEventFired('LinkScanner.resultsExported', $this->LinkScanner->getEventManager());
            $this->assertOutputContains('Results have been exported to ' . $expectedExportFile);
            $this->assertErrorEmpty();
        }

        //Enables follow redirects
        /** @var \LinkScanner\Utility\LinkScanner&\PHPUnit\Framework\MockObject\MockObject $LinkScanner */
        $LinkScanner = $this->getMockBuilder(LinkScanner::class)
            ->setConstructorArgs([$this->getClientReturnsRedirect()])
            ->onlyMethods(['_createLockFile'])
            ->getMock();
        $this->LinkScanner = $LinkScanner;

        $this->Command->LinkScanner = $this->LinkScanner;
        $this->getEventManager();
        array_pop($params);
        $this->Command->run(array_merge($params, ['--follow-redirects', '--no-cache']), $this->_io);
        $this->assertEventFired('LinkScanner.foundRedirect', $this->LinkScanner->getEventManager());
        $this->assertTrue($this->LinkScanner->getConfig()['followRedirects']);
        $this->assertOutputContains('Redirects will be followed');
        $this->assertOutputContains('Redirect found: http://localhost/redirectTarget');
        $this->assertOutputContains('Checking http://localhost/redirectTarget ...');

        //With an invalid full base url
        $this->expectException(StopException::class);
        $this->Command->run(['--full-base-url=invalid'], $this->_io);
    }

    /**
     * @test
     * @uses \LinkScanner\Command\LinkScannerCommand::execute()
     */
    public function testExecuteWithVerbose(): void
    {
        $this->Command->run(['--no-cache', '--verbose'], $this->_io);
        $this->assertOutputRegExp(sprintf('/Scan started for %s at [\d\-]+\s[\d\:]+/', preg_quote($this->fullBaseUrl, '/')));
        $this->assertOutputContains('The cache is disabled');
        $this->assertOutputContains('Force mode is not enabled');
        $this->assertOutputContains('Scanning of external links is enabled');
        $this->assertOutputContains('Redirects will not be followed');
        $this->assertOutputRegExp('/Timeout in seconds for GET requests: \d+/');

        //Moves to final lines
        $messages = array_values(array_filter($this->_out->messages()));
        $count = count($messages);
        while (key($messages) !== $count - 6) {
            next($messages);
        }

        $this->assertMatchesRegularExpression('/^\-+$/', current($messages) ?: '');
        $this->assertMatchesRegularExpression('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages) ?: '');
        $this->assertMatchesRegularExpression('/^Elapsed time: \d+ seconds?$/', next($messages) ?: '');
        $this->assertMatchesRegularExpression('/^Total scanned links: [1-9]\d*$/', next($messages) ?: '');
        $this->assertSame('Invalid links: 0', next($messages));
        $this->assertMatchesRegularExpression('/^\-+$/', next($messages));

        //Removes already checked lines and checks intermediate lines
        foreach (array_slice($messages, 9, -6) as $message) {
            $this->assertMatchesRegularExpression('/^(<success>OK<\/success>|Checking .+ \.{3}|Link found: .+)$/', $message);
        }
    }

    /**
     * @test
     * @uses \LinkScanner\Command\LinkScannerCommand::buildOptionParser()
     */
    public function testBuildOptionParser(): void
    {
        $parser = $this->invokeMethod($this->Command, 'buildOptionParser', [new ConsoleOptionParser()]);
        $this->assertInstanceOf(ConsoleOptionParser::class, $parser);
        $this->assertEquals('Performs a complete scan', $parser->getDescription());
        $this->assertEmpty($parser->arguments());
        $this->assertEquals([
            'export',
            'export-only-bad-results',
            'export-with-filename',
            'follow-redirects',
            'force',
            'full-base-url',
            'help',
            'max-depth',
            'no-cache',
            'no-external-links',
            'quiet',
            'timeout',
            'verbose',
        ], array_keys($parser->options()));
    }
}
