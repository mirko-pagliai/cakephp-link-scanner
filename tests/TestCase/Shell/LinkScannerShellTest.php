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
     * Internal method to set and get the `LinkScannerShell` instance and all
     *  properties of this test class
     */
    protected function setLinkScannerShell()
    {
        $this->LinkScanner = new LinkScanner($this->fullBaseUrl);
        $this->LinkScanner->Client = $this->getClientReturnsSampleResponse();
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
        $this->setLinkScannerShell();
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
        $this->LinkScannerShell->params['max-depth'] = 1;
        $this->LinkScannerShell->scan();

        $this->assertEquals([
            'excludeLinks' => ['\{.*\}', 'javascript:'],
            'externalLinks' => true,
            'followRedirects' => false,
            'maxDepth' => 1,
        ], $this->LinkScanner->getConfig());

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
        $this->assertEventNotFired(LINK_SCANNER . '.foundRedirect', $this->EventManager);
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
        $this->LinkScannerShell->params['full-base-url'] = 'invalid';
        $this->LinkScannerShell->scan();
    }

    /**
     * Test for `scan()` method, with some parameters
     * @test
     */
    public function testScanParams()
    {
        $expectedConfig = [
            'excludeLinks' => ['\{.*\}', 'javascript:'],
            'externalLinks' => true,
            'followRedirects' => false,
            'maxDepth' => 2,
        ];

        touch(LINK_SCANNER_LOCK_FILE);
        $params = [
            'export' => null,
            'force' => true,
            'max-depth' => 2,
            'timeout' => 15,
            'verbose' => true,
        ];
        $this->LinkScannerShell->params = $params;
        $this->LinkScannerShell->scan();

        $expectedExportFile = LINK_SCANNER_TARGET . DS . 'results_' . $this->LinkScanner->hostname . '_' . $this->LinkScanner->startTime;

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertEquals($params['timeout'], $this->LinkScanner->Client->getConfig('timeout'));

        $this->assertFileNotExists(LINK_SCANNER_LOCK_FILE);
        $this->assertFileExists($expectedExportFile);
        $this->assertEventFired(LINK_SCANNER . '.resultsExported', $this->EventManager);

        $messages = $this->out->messages();
        $this->assertRegexp('/^\-{63}$/', current($messages));
        $this->assertTextContains(sprintf('Scan started for %s', $this->fullBaseUrl), next($messages));
        $this->assertRegexp('/at [\d\-:\s]+<\/info>$/', current($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));
        $this->assertTextContains('The cache is disabled', next($messages));
        $this->assertTextContains('Force mode is enabled', next($messages));
        $this->assertTextContains('Scanning of external links is enabled', next($messages));
        $this->assertTextContains('Redirects will not be followed', next($messages));
        $this->assertTextContains('Maximum depth of the scan: 2', next($messages));
        $this->assertTextContains('Timeout in seconds for GET requests: 15', next($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));

        $this->assertTextContains(sprintf('Results have been exported to', $expectedExportFile), end($messages));

        $this->assertEmpty($this->err->messages());

        //Changes the full base url
        $params['full-base-url'] = 'http://anotherFullBaseUrl';
        $this->setLinkScannerShell();
        $this->LinkScannerShell->params = $params;
        $this->LinkScannerShell->scan();

        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());
        $this->assertEquals($params['full-base-url'], $this->LinkScanner->fullBaseUrl);

        $this->assertNotEmpty(preg_grep(sprintf('/Scan started for %s/', preg_quote($this->LinkScanner->fullBaseUrl, '/')), $this->out->messages()));
        $this->assertEmpty($this->err->messages());

        //Disables external links
        $params['full-base-url'] = $this->fullBaseUrl;
        $params += ['disable-external-links' => true, 'verbose' => true];
        $this->setLinkScannerShell();
        $this->LinkScannerShell->params = $params;
        $this->LinkScannerShell->scan();

        $this->assertEquals(['externalLinks' => false] + $expectedConfig, $this->LinkScanner->getConfig());

        $lineDifferentFullBaseUrl = function ($line) {
            $pattern = sprintf('/^Checking https?:\/\/%s/', preg_quote(get_hostname_from_url($this->fullBaseUrl)));

            return substr($line, 0, strlen('Checking')) === 'Checking' && !preg_match($pattern, $line);
        };
        $this->assertEmpty(array_filter($this->out->messages(), $lineDifferentFullBaseUrl));
        $this->assertNotEmpty(preg_grep('/Scanning of external links is not enabled/', $this->out->messages()));

        //Re-enables external links
        unset($params['disable-external-links']);
        $this->setLinkScannerShell();
        $this->LinkScannerShell->params = $params;
        $this->LinkScannerShell->scan();

        $expectedConfig['externalLinks'] = true;
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

        $this->assertNotEmpty(array_filter($this->out->messages(), $lineDifferentFullBaseUrl));

        foreach ([
            'example' => LINK_SCANNER_TARGET . DS . 'example',
            TMP . 'example' => TMP . 'example',
        ] as $filename => $expectedExportFile) {
            $this->setLinkScannerShell();
            $this->LinkScannerShell->params = ['export' => $filename] + $params;
            $this->LinkScannerShell->scan();

            $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

            $this->assertFileExists($expectedExportFile);
            $this->assertEventFired(LINK_SCANNER . '.resultsExported', $this->EventManager);

            $messages = $this->out->messages();
            $this->assertTextContains(sprintf('Results have been exported to', $expectedExportFile), end($messages));

            $this->assertEmpty($this->err->messages());
        }

        //Enables follow redirects
        $params = ['follow-redirects' => true, 'max-depth' => 1] + $params;
        $this->setLinkScannerShell();
        $this->LinkScannerShell->params = $params;
        $this->LinkScannerShell->scan();

        $expectedConfig = ['followRedirects' => true, 'maxDepth' => 1] + $expectedConfig;
        $this->assertEquals($expectedConfig, $this->LinkScanner->getConfig());

        $this->assertNotEmpty(preg_grep('/Redirects will be followed/', $this->out->messages()));
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

        //Initial lines
        $this->assertRegexp('/^\-{63}$/', current($messages));
        $this->assertTextStartsWith(sprintf('<info>Scan started for %s', $this->fullBaseUrl), next($messages));
        $this->assertRegexp(sprintf('/t [\d\-]+\s[\d\:]+<\/info>$/'), current($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));
        $this->assertTextContains('The cache is disabled', next($messages));
        $this->assertTextContains('Force mode is not enabled', next($messages));
        $this->assertTextContains('Scanning of external links is enabled', next($messages));
        $this->assertTextContains('Redirects will not be followed', next($messages));
        $this->assertRegexp('/Timeout in seconds for GET requests: \d+/', next($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));

        //Final lines
        while (key($messages) !== $count - 5) {
            next($messages);
        };
        $this->assertRegexp('/^\-{63}$/', current($messages));
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertTextStartsWith('Elapsed time: ', next($messages));
        $this->assertRegexp('/\d+ seconds?$/', current($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));

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
        $this->LinkScannerShell->LinkScanner->Client = $this->getClientReturnsErrorResponse();
        $this->LinkScannerShell->params['verbose'] = true;
        $this->LinkScannerShell->scan();

        $this->assertEquals(['<error>404</error>'], $this->err->messages());
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
            'disable-external-links',
            'export',
            'follow-redirects',
            'force',
            'full-base-url',
            'help',
            'max-depth',
            'quiet',
            'timeout',
            'verbose',
        ], $parser->subcommands()['scan']->parser()->options());
    }
}
