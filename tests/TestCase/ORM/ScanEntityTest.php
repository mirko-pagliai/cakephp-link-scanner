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
namespace LinkScanner\Test\ORM;

use LinkScanner\ORM\ScanEntity;
use LinkScanner\TestSuite\TestCase;

/**
 * ScanEntityTest class
 */
class ScanEntityTest extends TestCase
{
    /**
     * Test for `isOk()` method (through the `__call()` method)
     * @test
     */
    public function isOkTest()
    {
        $statusCodes = [
            200 => true,
            301 => false,
            404 => false,
        ];

        foreach ($statusCodes as $code => $expectedValue) {
            $entity = new ScanEntity(compact('code') + ['location' => '/']);
            $this->assertEquals($expectedValue, $entity->isOk());
        }
    }

    /**
     * Test for `isRedirect()` method (through the `__call()` method)
     * @test
     */
    public function isRedirectTest()
    {
        $statusCodes = [
            200 => false,
            301 => true,
            404 => false,
        ];

        foreach ($statusCodes as $code => $expectedValue) {
            $entity = new ScanEntity(compact('code') + ['location' => '/']);
            $this->assertEquals($expectedValue, $entity->isRedirect());
        }
    }
}
