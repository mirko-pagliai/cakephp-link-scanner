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
     * Magic method, is triggered when invoking inaccessible methods. It calls
     *  the same method name from the original `Response` class
     * @param string $name Method name
     * @param mixed $arguments Method arguments
     * @return mixed
     * @see Cake\Http\Client\Response
     */
    public function __call($name, $arguments)
    {
        if (method_exists(Response::class, $name)) {
            $properties = $this->_properties + ['location' => null];
            $response = (new Response)->withHeader('location', $properties['location'])
                ->withStatus($properties['code']);

            $name = [$response, $name];
        }

        return call_user_func_array($name, $arguments);
    }

    /**
     * Checks if the response is error
     * @return bool
     */
    public function isError()
    {
        return !$this->isOk() && !$this->isRedirect();
    }
}
