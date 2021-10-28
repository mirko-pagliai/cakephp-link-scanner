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
use Cake\TestSuite\Stub\ConsoleOutput;
use LinkScanner\Command\LinkScannerCommand;
use LinkScanner\TestSuite\TestCase;
use LinkScanner\Utility\LinkScanner;
use MeTools\TestSuite\ConsoleIntegrationTestTrait;
use PHPUnit\Framework\Error\Deprecated;

/**
 * LinkScannerCommandTest class
 * @property \LinkScanner\Command\LinkScannerCommand $Command
 */
class LinkScannerCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @var \LinkScanner\Utility\LinkScanner|(\LinkScanner\Utility\LinkScanner&\PHPUnit\Framework\MockObject\MockObject)
     */
    protected $LinkScanner;

    /**
     * @var string
     */
    protected $fullBaseUrl = 'http://google.com';

    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected $_io;

    /**
     * Called before every test method
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->LinkScanner = new LinkScanner($this->getClientReturnsSampleResponse());
        $this->LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl);
        $this->getEventManager();

        $this->_out = new ConsoleOutput();
        $this->_err = new ConsoleOutput();
        $this->_io = new ConsoleIo($this->_out, $this->_err);
        $this->_io->level(2);

        $this->Command = new LinkScannerCommand();
        $this->Command->LinkScanner = $this->LinkScanner;
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan(): void
    {
        $expectedConfig = ['maxDepth' => 1] + $this->LinkScanner->getConfig();
        $this->Command->run(['--max-depth=1'], $this->_io);

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

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
        $this->Command->LinkScanner = new LinkScanner($this->getClientReturnsErrorResponse());
        $this->Command->LinkScanner->setConfig('fullBaseUrl', $this->fullBaseUrl);
        $this->Command->run(['--verbose'], $this->_io);
        $this->assertErrorContains('404');

        //Does not suppress PHPUnit exceptions, which are throwned anyway
        $this->expectDeprecation();
        $Client = $this->getClientStub();
        $Client->method('get')->will($this->throwException(new Deprecated('This is deprecated', 0, __FILE__, __LINE__)));
        $this->Command->LinkScanner = new LinkScanner($Client);
        $this->Command->run(['--verbose'], $this->_io);
    }

    /**
     * Test for `scan()` method, with cache enabled
     * @test
     */
    public function testScanCacheEnabled(): void
    {
        $this->Command->run(['--verbose'], $this->_io);
        $expectedDuration = Cache::getConfig('LinkScanner')['duration'];
        $this->assertOutputContains(sprintf('The cache is enabled and its duration is `%s`', $expectedDuration));
    }

    /**
     * Test for `scan()` method, with some parameters
     * @test
     */
    public function testScanParams(): void
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
        $expectedFilename = $this->LinkScanner->getConfig('target') . DS . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime;

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertEquals(15, $this->LinkScanner->Client->getConfig('timeout'));
        $this->assertFileDoesNotExist($this->LinkScanner->lockFile);
        $this->assertFileExists($expectedFilename);
        $this->assertEventFired('LinkScanner.resultsExported', $this->LinkScanner->getEventManager());
        $this->assertOutputRegExp(sprintf('/Scan started for %s/', preg_quote($this->fullBaseUrl, '/')));
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
        $this->assertErrorEmpty();

        //Exports only bad results
        self::setUp();
        $this->Command->run(array_merge($params, ['--export-only-bad-results']), $this->_io);
        $this->assertEquals(['exportOnlyBadResults' => true] + $expectedConfig, $this->LinkScanner->getConfig());

        //Disables external links
        array_pop($params);
        $params = array_merge($params, ['--full-base-url=' . $this->fullBaseUrl, '--no-external-links']);

        self::setUp();
        $this->Command->run($params, $this->_io);
        $expectedConfig['externalLinks'] = false;
        $expectedConfig['fullBaseUrl'] = $this->fullBaseUrl;
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

        $differentLines = function (string $line): bool {
            $pattern = sprintf('/^Checking https?:\/\/%s/', preg_quote(get_hostname_from_url($this->fullBaseUrl)));

            return substr($line, 0, strlen('Checking')) === 'Checking' && !preg_match($pattern, $line);
        };
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
            'example' => $this->LinkScanner->getConfig('target') . DS . 'example',
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
            ->setMethods(['_createLockFile'])
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
     * Test for `scan()` method, with verbose
     * @test
     */
    public function testScanVerbose(): void
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
        while (key($messages) !== $count - 5) {
            next($messages);
        }

        $this->assertMatchesRegularExpression('/^\-+$/', current($messages) ?: '');
        $this->assertMatchesRegularExpression('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages) ?: '');
        $this->assertMatchesRegularExpression('/^Elapsed time: \d+ seconds?$/', next($messages) ?: '');
        $this->assertMatchesRegularExpression('/^Total scanned links: \d+$/', next($messages) ?: '');
        $this->assertMatchesRegularExpression('/^\-+$/', next($messages) ?: '');

        //Removes already checked lines and checks intermediate lines
        foreach (array_slice($messages, 9, -5) as $message) {
            $this->assertMatchesRegularExpression('/^(<success>OK<\/success>|Checking .+ \.{3}|Link found: .+)$/', $message);
        }
    }

    /**
     * Test for `buildOptionParser()` method
     * @test
     */
    public function testBuildOptionParser(): void
    {
        $parser = $this->invokeMethod($this->Command, 'buildOptionParser', [new ConsoleOptionParser()]);
        $this->assertInstanceOf(ConsoleOptionParser::class, $parser);
        $this->assertEquals('Performs a complete scan', $parser->getDescription());
        $this->assertEmpty($parser->arguments());
        $this->assertArrayKeysEqual([
            'export-only-bad-results',
            'no-external-links',
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
