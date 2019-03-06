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
namespace LinkScanner\Test;

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
    protected $ScanEntity;

    /**
     * Setup the test case, backup the static object values so they can be
     * restored. Specifically backs up the contents of Configure and paths in
     *  App if they have not already been backed up
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->ScanEntity = new ScanEntity([
            'code' => 200,
            'external' => false,
            'location' => 'http://example.com/location',
            'type' => 'text/html; charset=UTF-8',
            'url' => 'http://example.com',
        ]);
    }

    /**
     * Test for `ArrayAccess` interface
     * @test
     */
    public function testArrayAccess()
    {
        $this->ScanEntity['newKey'] = 'a key';
        $this->assertTrue(isset($this->ScanEntity['newKey']));
        $this->assertSame('a key', $this->ScanEntity['newKey']);
        unset($this->ScanEntity['newKey']);
        $this->assertFalse(isset($this->ScanEntity['newKey']));
        $this->assertNull($this->ScanEntity['newKey']);
    }

    /**
     * Test for `__call()`
     * @test
     */
    public function testCall()
    {
        $statusCodes = [
            200 => true,
            301 => false,
            404 => false,
        ];

        foreach ($statusCodes as $code => $expectedValue) {
            $this->ScanEntity->offsetSet('code', $code);
            $this->assertEquals($expectedValue, $this->ScanEntity->isOk());
        }
        $statusCodes = [
            200 => false,
            301 => true,
            404 => false,
        ];

        foreach ($statusCodes as $code => $expectedValue) {
            $this->ScanEntity->offsetSet('code', $code);
            $this->assertEquals($expectedValue, $this->ScanEntity->isRedirect());
        }
    }

    /**
     * Test for `__construct()` method
     * @expectedException Tools\Exception\KeyNotExistsException
     * @expectedExceptionMessage Key `code` does not exist
     * @test
     */
    public function testConstruct()
    {
        new ScanEntity;
    }

    /**
     * Test for `__debugInfo()` method
     * @test
     */
    public function testDebugInfo()
    {
        ob_start();
        $line = __LINE__ + 1;
        var_dump($this->ScanEntity);
        $dump = ob_get_contents();
        ob_end_clean();
        $this->assertTextContains(get_class($this->ScanEntity), $dump);

        $this->skipIf(IS_WIN);
        $this->assertTextContains($line, $dump);
        $this->assertTextContains(__FILE__, $dump);
    }

    /**
     * Test for `__get()` method
     * @test
     */
    public function testGet()
    {
        $this->assertSame(200, $this->ScanEntity->code);
        $this->assertNull($this->ScanEntity->noExisting);
    }

    /**
     * Test for `has()` method
     * @test
     */
    public function testHas()
    {
        $this->assertTrue($this->ScanEntity->has('code'));
        $this->assertFalse($this->ScanEntity->has('noExisting'));
    }
}
