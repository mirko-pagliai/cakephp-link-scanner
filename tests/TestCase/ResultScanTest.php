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
namespace LinkScanner\Test\TestCase;

use Cake\TestSuite\TestCase;
use LinkScanner\ResultScan;
use LinkScanner\ScanEntity;

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
     * Called before every test method
     * @return void
     */
    public function setUp(): void
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
    }

    /**
     * Test for `append()` method
     * @test
     */
    public function testAppend()
    {
        $appended = [
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
        $result = $this->ResultScan->append($appended);
        $this->assertInstanceof(ResultScan::class, $result);
        $this->assertEquals(array_merge($existing, $appended), $result->toArray());
        $this->assertCount(3, $result);

        //With a new `ResultScan`
        $result = (new ResultScan())->append($appended);
        $this->assertEquals($appended, $result->toArray());
        $this->assertCount(2, $result);
    }

    /**
     * Test for `prepend()` method
     * @test
     */
    public function testPrepend()
    {
        $prepended = [
            new ScanEntity([
                'code' => 200,
                'external' => true,
                'type' => 'text/html; charset=UTF-8',
                'url' => 'http://example.com/',
            ]),
        ];
        $existing = $this->ResultScan->toArray();
        $result = $this->ResultScan->prepend($prepended);
        $this->assertInstanceof(ResultScan::class, $result);
        $this->assertEquals(array_merge($prepended, $existing), $result->toArray());
        $this->assertCount(2, $result);

        //With a new `ResultScan`
        $result = (new ResultScan())->prepend($prepended);
        $this->assertEquals($prepended, $result->toArray());
        $this->assertCount(1, $result);
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
