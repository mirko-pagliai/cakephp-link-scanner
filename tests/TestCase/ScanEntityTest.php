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

use BadMethodCallException;
use LinkScanner\ScanEntity;
use LinkScanner\TestSuite\TestCase;

/**
 * ScanEntityTest class
 */
class ScanEntityTest extends TestCase
{
    /**
     * @var \LinkScanner\ScanEntity
     */
    protected ScanEntity $ScanEntity;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->ScanEntity ??= new ScanEntity([
            'code' => 200,
            'external' => false,
            'location' => 'https://example.com/location',
            'type' => 'text/html; charset=UTF-8',
            'url' => 'https://example.com',
        ]);
    }

    /**
     * @test
     * @uses \LinkScanner\ScanEntity::__call()
     */
    public function testCall(): void
    {
        foreach ([
             200 => true,
             301 => false,
             404 => false,
         ] as $code => $expectedValue) {
            $this->ScanEntity['code'] = $code;
            $this->assertEquals($expectedValue, $this->ScanEntity->isSuccess());
        }

        foreach ([
             200 => false,
             301 => true,
             404 => false,
         ] as $code => $expectedValue) {
            $this->ScanEntity['code'] = $code;
            $this->assertEquals($expectedValue, $this->ScanEntity->isRedirect());
        }

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method `noExistingMethod()` does not exist');
        /** @noinspection PhpUndefinedMethodInspection */
        $this->ScanEntity->noExistingMethod(1);
    }

    /**
     * @test
     * @uses \LinkScanner\ScanEntity::__construct()
     */
    public function testConstruct(): void
    {
        $this->expectExceptionMessage('Key `code` does not exist');
        new ScanEntity();
    }

    /**
     * @test
     * @uses \LinkScanner\ScanEntity::offsetExists()
     * @uses \LinkScanner\ScanEntity::offsetGet()
     * @uses \LinkScanner\ScanEntity::offsetSet()
     * @uses \LinkScanner\ScanEntity::offsetUnset()
     */
    public function testOffsetMethods(): void
    {
        $this->ScanEntity->key = 'value';
        $this->assertSame('value', $this->ScanEntity->key);
        $this->assertTrue(isset($this->ScanEntity->key));
        unset($this->ScanEntity->key);
        $this->assertFalse(isset($this->ScanEntity->key));
    }
}
