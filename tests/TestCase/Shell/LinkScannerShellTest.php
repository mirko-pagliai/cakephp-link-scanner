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

        $this->LinkScannerShell = $this->getMockBuilder(LinkScannerShell::class)
            ->setMethods(['in', '_stop'])
            ->setConstructorArgs([$io])
            ->getMock();

        $this->LinkScannerShell->LinkScanner = $this->getLinkScannerClientReturnsSampleResponse();

        $this->EventManager = $this->getEventManager($this->LinkScannerShell->LinkScanner);
    }

    /**
     * Teardown any static object changes and restore them
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        //Deletes all export files
        foreach (glob(TMP . 'scanExport*') as $filename) {
            //@codingStandardsIgnoreLine
            @unlink($filename);
        }
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
        $this->LinkScannerShell->scan($filename);
        $this->assertFileExists($filename);

        $messages = $this->out->messages();
        $this->assertCount(4, $messages);
        $this->assertRegexp(
            sprintf('/^Scan started for %s at [\d\-]+\s[\d\:]+$/', preg_quote($this->LinkScannerShell->LinkScanner->fullBaseUrl, '/')),
            current($messages)
        );
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));
        $this->assertRegexp(
            sprintf('/^<success>Results have been exported to %s<\/success>$/', preg_quote($filename, DS)),
            next($messages)
        );
        $this->assertEmpty($this->err->messages());

        foreach ([
            'scanStarted',
            'scanCompleted',
            'beforeScanUrl',
            'afterScanUrl',
            'foundLinksToBeScanned',
            'resultsExported',
        ] as $eventName) {
            $this->assertEventFired(LINK_SCANNER . '.' . $eventName, $this->EventManager);
        }
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
        $this->LinkScannerShell->params = [
            'maxDepth' => 3,
            'fullBaseUrl' => 'http://anotherFullBaseUrl',
            'timeout' => 1,
        ];
        $this->LinkScannerShell->scan($this->getTempname());

        $this->assertStringStartsWith('Scan started for ' . $this->LinkScannerShell->params['fullBaseUrl'], $this->out->messages()[0]);
        $this->assertEmpty($this->err->messages());

        foreach ($this->LinkScannerShell->params as $name => $value) {
            $this->assertEquals($value, $this->LinkScannerShell->LinkScanner->{$name});
        }
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
        $messages = $this->out->messages();
        $count = count($messages);

        //First three lines
        $this->assertRegexp('/^\-{63}$/', $messages[0]);
        $this->assertRegexp(
            sprintf('/^Scan started for %s at [\d\-]+\s[\d\:]+$/', preg_quote($this->LinkScannerShell->LinkScanner->fullBaseUrl, '/')),
            $messages[1]
        );
        $this->assertRegexp('/^\-{63}$/', $messages[2]);

        //Last five lines
        $this->assertRegexp(
            sprintf('/^<success>Results have been exported to %s<\/success>$/', preg_quote($filename, DS)),
            $messages[$count - 1]
        );
        $this->assertRegexp('/^\-{63}$/', $messages[$count - 2]);
        $this->assertRegexp('/^Total scanned links: \d+$/', $messages[$count - 3]);
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', $messages[$count - 4]);
        $this->assertRegexp('/^\-{63}$/', $messages[$count - 5]);

        //Removes the already checked lines
        foreach ([0, 1, 2, $count - 1, $count - 2, $count - 3, $count - 4, $count - 5] as $line) {
            unset($messages[$line]);
        }

        $messages = array_values($messages);
        $count = count($messages);

        //Checks the intermediate lines
        for ($line = 0; $line < $count;) {
            $this->assertTextStartsWith('Checking ', $messages[$line]);
            $this->assertTextEndsWith(' ...', $messages[$line]);
            $line++;
            $this->assertEquals('<success>OK</success>', $messages[$line++]);
            $this->assertRegexp('/^Links found: \d+$/', $messages[$line++]);
        }
    }

    /**
     * Test for `getOptionParser()` method
     * @test
     */
    public function testGetOptionParser()
    {
        $parser = $this->LinkScannerShell->getOptionParser();

        $this->assertInstanceOf('Cake\Console\ConsoleOptionParser', $parser);
        $this->assertEquals(['scan'], array_keys($parser->subcommands()));
        $this->assertEquals('Shell to perform links scanner', $parser->getDescription());
        $this->assertEquals(['help', 'quiet', 'verbose'], array_keys($parser->options()));

        //`scan` subcommand
        $scanSubcommandParser = $parser->subcommands()['scan']->parser();

        //Tests arguments
        $this->assertCount(1, $scanSubcommandParser->arguments());
        $this->assertEquals('filename', $scanSubcommandParser->arguments()[0]->name());
        $this->assertTrue($scanSubcommandParser->arguments()[0]->isRequired());

        //Tests options
        $this->assertEquals([
            'fullBaseUrl',
            'help',
            'maxDepth',
            'quiet',
            'timeout',
            'verbose',
        ], array_keys($scanSubcommandParser->options()));
    }
}
