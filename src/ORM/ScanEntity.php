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
namespace LinkScanner\ORM;

use Cake\Http\Client\Response;
use Cake\ORM\Entity;

/**
 * An `ScanEntity` represents a single result of a scan.
 *
 * This class simulates the `Entity` class.
 */
class ScanEntity extends Entity
{
    /**
     * Check if the response was OK
     * @return bool
     */
    public function isOk()
    {
        $response = (new Response)->withStatus($this->_properties['code']);

        return $response->isOk();
    }
}
