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
            $this->ScanEntity->set('code', $code);
            $this->assertEquals($expectedValue, $this->ScanEntity->isOk());
        }
        $statusCodes = [
            200 => false,
            301 => true,
            404 => false,
        ];

        foreach ($statusCodes as $code => $expectedValue) {
            $this->ScanEntity->set('code', $code);
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
}
