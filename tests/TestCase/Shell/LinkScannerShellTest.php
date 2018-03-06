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
    }

    /**
     * Test for `scan()` method
     * @test
     */
    public function testScan()
    {
        $this->LinkScannerShell->scan();
        $messages = $this->out->messages();
        $this->assertCount(3, $messages);
        $this->assertRegexp(
            '/^Scan started for ' . preg_quote('http://google.com/', '/') . ' at [\d\-]+\s[\d\:]+$/',
            current($messages)
        );
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', next($messages));
        $this->assertRegexp('/^Total scanned links: \d+$/', next($messages));
        $this->assertEmpty($this->err->messages());
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
        $this->LinkScannerShell->params = [
            'maxDepth' => 3,
            'fullBaseUrl' => 'http://anotherFullBaseUrl/',
            'timeout' => 1,
        ];
        $this->LinkScannerShell->scan();

        $this->assertStringStartsWith('Scan started for http://anotherFullBaseUrl/', $this->out->messages()[0]);
        $this->assertEmpty($this->err->messages());

        foreach ($this->LinkScannerShell->params as $name => $value) {
            $this->assertEquals($value, $this->LinkScannerShell->LinkScanner->{$name});
        }

        //Resets
        $this->setUp();

        //Tries with the `export` param
        $export = tempnam(TMP, 'scan');
        $this->LinkScannerShell->params = compact('export');
        $this->LinkScannerShell->scan();

        $this->assertContains('Results have been exported to ' . $export, $this->out->messages());
        $this->assertEmpty($this->err->messages());
        $this->assertFileExists($export);
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

        //First three lines
        $this->assertRegexp('/^\-{63}$/', $messages[0]);
        $this->assertRegexp('/^Scan started for ' . preg_quote('http://google.com/', '/') . ' at [\d\-]+\s[\d\:]+$/', $messages[1]);
        $this->assertRegexp('/^\-{63}$/', $messages[2]);

        //Last four lines
        $this->assertRegexp('/^\-{63}$/', $messages[$count - 1]);
        $this->assertRegexp('/^Total scanned links: \d+$/', $messages[$count - 2]);
        $this->assertRegexp('/^Scan completed at [\d\-]+\s[\d\:]+$/', $messages[$count - 3]);
        $this->assertRegexp('/^\-{63}$/', $messages[$count - 4]);

        //Removes the already checked lines
        foreach ([0, 1, 2, $count - 1, $count - 2, $count - 3, $count - 4] as $line) {
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
        $this->assertEquals([
            'export',
            'fullBaseUrl',
            'help',
            'maxDepth',
            'quiet',
            'timeout',
            'verbose',
        ], array_keys($scanSubcommandParser->options()));
    }
}
