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
     * Setup the test case, backup the static object values so they can be
     * restored. Specifically backs up the contents of Configure and paths in
     *  App if they have not already been backed up
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->out = new ConsoleOutput;
        $this->err = new ConsoleOutput;
        $io = new ConsoleIo($this->out, $this->err);
        $io->level(2);

        $this->fullBaseUrl = 'http://google.com';

        $this->LinkScannerShell = $this->getMockBuilder(LinkScannerShell::class)
            ->setConstructorArgs([$io])
            ->setMethods(['in', '_stop'])
            ->getMock();

        $this->LinkScannerShell->LinkScanner = $this->getLinkScannerClientReturnsSampleResponse($this->fullBaseUrl);

        $this->EventManager = $this->getEventManager($this->LinkScannerShell->LinkScanner);
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
     * Internal method to create and get a temporary file with unique file name
     * @return string
     */
    protected function getTempname()
    {
        return tempnam(TMP, 'scanExport');
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $filename = $this->getTempname();
        $this->LinkScannerShell->params['maxDepth'] = 1;
        $this->LinkScannerShell->scan($filename);
        $this->assertFileExists($filename);

        $messages = $this->out->messages();
        $this->assertCount(5, $messages);

        $this->assertTextStartsWith(sprintf('<info>Scan started for %s', $this->fullBaseUrl), current($messages));
        $this->assertRegexp('/at [\d\-]+\s[\d\:]+<\/info>$/', current($messages));
        $this->assertEquals(sprintf('Checking %s ...', $this->fullBaseUrl), next($messages));
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));
        $this->assertTextContains(sprintf('Results have been exported to %s', $filename), next($messages));
        $this->assertEmpty($this->err->messages());

        foreach ([
            'scanStarted',
            'scanCompleted',
            'beforeScanUrl',
            'afterScanUrl',
            'resultsExported',
        ] as $eventName) {
            $this->assertEventFired(LINK_SCANNER . '.' . $eventName, $this->EventManager);
        }

        $this->assertEventNotFired(LINK_SCANNER . '.foundLinkToBeScanned', $this->EventManager);
    }

    /**
     * Test for `scan()` method, with cache enabled
     * @test
     */
    public function testScanCacheEnabled()
    {
        Cache::enable();

        $this->LinkScannerShell->params['verbose'] = true;
        $this->LinkScannerShell->scan($this->getTempname());

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
        $this->LinkScannerShell->scan($this->getTempname());
    }

    /**
     * Test for `scan()` method, with some parameters
     * @test
     */
    public function testScanParams()
    {
        $filename = $this->getTempname();
        $params = [
            'fullBaseUrl' => 'http://anotherFullBaseUrl',
            'maxDepth' => 3,
            'timeout' => 15,
        ];

        $this->LinkScannerShell->params = $params;
        $this->LinkScannerShell->scan($filename);
        $this->assertFileExists($filename);

        $this->assertTextContains('Scan started for ' . $params['fullBaseUrl'], $this->out->messages()[0]);
        $this->assertEmpty($this->err->messages());

        $this->assertEquals($params['fullBaseUrl'], $this->getProperty($this->LinkScannerShell->LinkScanner, 'fullBaseUrl'));
        $this->assertEquals($params['maxDepth'], $this->LinkScannerShell->LinkScanner->getConfig('maxDepth'));
        $this->assertEquals($params['timeout'], $this->LinkScannerShell->LinkScanner->Client->getConfig('timeout'));
    }

    /**
     * Test for `scan()` method, with verbose
     * @test
     */
    public function testScanVerbose()
    {
        $filename = $this->getTempname();
        $this->LinkScannerShell->params['verbose'] = true;
        $this->LinkScannerShell->scan($filename);
        $this->assertFileExists($filename);

        $messages = $this->out->messages();
        $count = count($messages);

        //First five lines
        $this->assertRegexp('/^\-{63}$/', current($messages));
        $this->assertTextStartsWith(sprintf('<info>Scan started for %s', $this->fullBaseUrl), next($messages));
        $this->assertRegexp(sprintf('/t [\d\-]+\s[\d\:]+<\/info>$/'), current($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));
        $this->assertTextContains('The cache is disabled', next($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));

        //Last five lines
        while (key($messages) !== $count - 5) {
            next($messages);
        };
        $this->assertRegexp('/^\-{63}$/', current($messages));
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));
        $this->assertRegexp('/^\-{63}$/', next($messages));
        $this->assertTextStartsWith('<success>Results have been exported to ', next($messages));
        $this->assertTextEndsWith(sprintf('%s</success>', $filename), current($messages));

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
        $this->LinkScannerShell->LinkScanner = $this->getLinkScannerClientReturnsError($this->fullBaseUrl);

        $this->LinkScannerShell->params['verbose'] = true;
        $this->LinkScannerShell->scan($this->getTempname());

        $errors = $this->err->messages();
        $this->assertCount(1, $errors);
        $this->assertEquals('<warning>404</warning>', current($errors));
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

        //`scan` subcommand
        $scanSubcommandParser = $parser->subcommands()['scan']->parser();

        //Tests arguments
        $this->assertCount(1, $scanSubcommandParser->arguments());
        $this->assertEquals('filename', $scanSubcommandParser->arguments()[0]->name());
        $this->assertTrue($scanSubcommandParser->arguments()[0]->isRequired());

        //Tests options
        $this->assertArrayKeysEqual([
            'fullBaseUrl',
            'help',
            'maxDepth',
            'quiet',
            'timeout',
            'verbose',
        ], $scanSubcommandParser->options());
    }
}
