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

        $this->ResultScan = new ResultScan($expected);
        $this->assertEquals($expected, $this->ResultScan->toArray());

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
     * Test for `__call()` method
     * @test
     */
    public function testCall()
    {
        $expected = [new ScanEntity([
            'code' => 200,
            'external' => true,
            'type' => 'text/html; charset=UTF-8',
            'url' => 'http://google.com',
        ])];
        $this->assertEquals($expected, $this->ResultScan->toArray());
        $this->assertEquals($expected, $this->ResultScan->toList());

        $this->ResultScan->appendItem([
            'code' => 200,
            'external' => false,
            'type' => 'text/html;charset=UTF-8',
            'url' => 'http://example.com/',
        ]);
        $this->assertEquals($expected[0], $this->ResultScan->first());

        $result = $this->ResultScan->map(function ($item) {
            $item['exampleKey'] = 'exampleValue';

            return $item;
        });
        $this->assertInstanceof(CollectionInterface::class, $result);
        $this->assertEquals([
            0 => 'exampleValue',
            1 => 'exampleValue',
        ], $result->extract('exampleKey')->toArray());
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
        $this->assertEquals(array_merge($existing, $expected), $this->ResultScan->toArray());

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
     * Test for `appendItem()` method
     * @test
     */
    public function testAppendItem()
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
        $result = $this->ResultScan->appendItem($expected[0])->appendItem($expected[1]);
        $this->assertInstanceof(ResultScan::class, $result);
        $this->assertEquals(array_merge($existing, $expected), $this->ResultScan->toArray());

        //Missing `code` key
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing data in the item to be appended');
        $this->ResultScan->appendItem(new ScanEntity([
            'external' => true,
            'type' => 'text/html; charset=UTF-8',
            'url' => 'http://example.com/anotherpage.html',
        ]));
    }

    /**
     * Test for `count()` method
     * @test
     */
    public function testCount()
    {
        $this->assertEquals(1, $this->ResultScan->count());

        $this->ResultScan->appendItem([
            'code' => 200,
            'external' => false,
            'type' => 'text/html;charset=UTF-8',
            'url' => 'http://example.com/',
        ]);

        $this->assertEquals(2, $this->ResultScan->count());

        $this->assertEquals(0, (new ResultScan([]))->count());
    }

    /**
     * Test for `getIterator()` method
     * @test
     */
    public function testGetIterator()
    {
        $this->assertInstanceof(Collection::class, $this->ResultScan->getIterator());
    }

    /**
     * Test for `serialize()` and `unserialize()` methods
     * @test
     */
    public function testSerializeAndUnserialize()
    {
        $serialized = serialize($this->ResultScan);
        $this->assertTrue(is_string($serialized));

        $result = @unserialize($serialized);
        $this->assertInstanceof(ResultScan::class, $result);
        $this->assertEquals($result, $this->ResultScan);
        $this->assertEquals($result->toArray(), $this->ResultScan->toArray());
    }
}
