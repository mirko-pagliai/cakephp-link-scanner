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
namespace LinkScanner\Test\TestCase;

use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use Cake\TestSuite\TestCase;
use LinkScanner\ORM\ScanEntity;
use LinkScanner\ResultScan;
use LogicException;

/**
 * ResultScanTest class
 */
class ResultScanTest extends TestCase
{
    /**
     * @var \LinkScanner\ResultScan
     */
    protected $ResultScan;

    /**
     * Setup the test case, backup the static object values so they can be
     * restored. Specifically backs up the contents of Configure and paths in
     *  App if they have not already been backed up
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->ResultScan = new ResultScan([new ScanEntity([
            'code' => 200,
            'external' => true,
            'type' => 'text/html; charset=UTF-8',
            'url' => 'http://google.com',
        ])]);
    }

    /**
     * Test for `__construct()` method
     * @test
     */
    public function testConstruct()
    {
        $expected = [
            new ScanEntity([
                'code' => 200,
                'external' => true,
                'type' => 'text/html; charset=UTF-8',
                'url' => 'http://google.com',
            ]),
            new ScanEntity([
                'code' => 200,
                'external' => false,
                'type' => 'text/html;charset=UTF-8',
                'url' => 'http://example.com/',
            ]),
        ];
        $this->assertEquals($expected, (new ResultScan($expected))->toArray());

        //Missing `code` key
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing data in the item to be appended');
        new ResultScan([[
            'external' => true,
            'type' => 'text/html; charset=UTF-8',
            'url' => 'http://google.com',
        ]]);
    }

    /**
     * Test for `append()` method
     * @test
     */
    public function testAppend()
    {
        $expected = [
            new ScanEntity([
                'code' => 200,
                'external' => true,
                'type' => 'text/html; charset=UTF-8',
                'url' => 'http://example.com/',
            ]),
            new ScanEntity([
                'code' => 200,
                'external' => false,
                'type' => 'text/html; charset=UTF-8',
                'url' => 'http://example.com/page.html',
            ]),
        ];
        $existing = $this->ResultScan->toArray();
        $result = $this->ResultScan->append($expected);
        $this->assertInstanceof(ResultScan::class, $result);
        $this->assertEquals(array_merge($existing, $expected), $result->toArray());

        //Missing `code` key
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing data in the item to be appended');
        $this->ResultScan->append([
            new ScanEntity([
                'external' => true,
                'type' => 'text/html; charset=UTF-8',
                'url' => 'http://example.com/anotherpage.html',
            ]),
        ]);
    }

    /**
     * Test for `serialize()` and `unserialize()` methods
     * @test
     */
    public function testSerializeAndUnserialize()
    {
        $result = unserialize(serialize($this->ResultScan));
        $this->assertInstanceof(ResultScan::class, $result);
        $this->assertEquals($result->toArray(), $this->ResultScan->toArray());
    }
}
