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
namespace LinkScanner;

use Cake\Collection\CollectionInterface;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use LinkScanner\ResultScan;

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

        $this->ResultScan = new ResultScan([new Entity([
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
            new Entity([
                'code' => 200,
                'external' => true,
                'type' => 'text/html; charset=UTF-8',
                'url' => 'http://google.com',
            ]),
            new Entity([
                'code' => 200,
                'external' => false,
                'type' => 'text/html;charset=UTF-8',
                'url' => 'http://example.com/',
            ]),
        ];

        $this->ResultScan = new ResultScan($expected);
        $this->assertEquals($expected, $this->ResultScan->toArray());
    }

    /**
     * Test for `__construct()` method
     * @expectedException LogicException
     * @expectedExceptionMessage Missing data in the item to be appended
     * @test
     */
    public function testConstructMissingData()
    {
        //Missing `code` key
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
        $expected = [new Entity([
            'code' => 200,
            'external' => true,
            'type' => 'text/html; charset=UTF-8',
            'url' => 'http://google.com',
        ])];
        $this->assertEquals($expected, $this->ResultScan->toArray());
        $this->assertEquals($expected, $this->ResultScan->toList());

        $this->ResultScan->append([
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
        $result = $this->ResultScan->append([
            'code' => 200,
            'external' => false,
            'type' => 'text/html;charset=UTF-8',
            'url' => 'http://example.com/',
        ]);

        $this->assertInstanceof(ResultScan::class, $result);
        $this->assertEquals([
            new Entity([
                'code' => 200,
                'external' => true,
                'type' => 'text/html; charset=UTF-8',
                'url' => 'http://google.com',
            ]),
            new Entity([
                'code' => 200,
                'external' => false,
                'type' => 'text/html;charset=UTF-8',
                'url' => 'http://example.com/',
            ]),
        ], $this->ResultScan->toArray());
    }

    /**
     * Test for `append()` method, with missing data
     * @expectedException LogicException
     * @expectedExceptionMessage Missing data in the item to be appended
     * @test
     */
    public function testAppendMissingData()
    {
        //Missing `code` key
        $this->ResultScan->append([
            'external' => false,
            'type' => 'text/html;charset=UTF-8',
            'url' => 'http://example.com/',
        ]);
    }

    /**
     * Test for `count()` method
     * @test
     */
    public function testCount()
    {
        $this->assertEquals(1, $this->ResultScan->count());

        $this->ResultScan->append([
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
        $this->assertInstanceof('Cake\Collection\Collection', $this->ResultScan->getIterator());
    }

    /**
     * Test for `serialize()` and `unserialize()` methods
     * @test
     */
    public function testSerializeAndUnserialize()
    {
        $serialized = serialize($this->ResultScan);
        $this->assertTrue(is_string($serialized));

        $result = safe_unserialize($serialized);

        $this->assertInstanceof(ResultScan::class, $result);
        $this->assertEquals($result, $this->ResultScan);
        $this->assertEquals($result->toArray(), $this->ResultScan->toArray());
    }
}
