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

use Cake\TestSuite\TestCase;
use LinkScanner\ORM\ScanEntity;

/**
 * ScanEntityTest class
 */
class ScanEntityTest extends TestCase
{
    /**
     * Test for `isOk()` method
     * @test
     */
    public function isOkTest()
    {
        //2xx Success
        foreach (range(200, 204) as $code) {
            $entity = new ScanEntity(compact('code'));
            $this->assertTrue($entity->isOk());
        }

        $noOkCodes = array_merge(
            range(205, 207),
            range(300, 308),
            range(400, 418),
            [420, 422, 426, 449, 451],
            range(500, 505),
            [509]
        );

        foreach ($noOkCodes as $code) {
            $entity = new ScanEntity(compact('code'));
            $this->assertFalse($entity->isOk());
        }
    }
}
