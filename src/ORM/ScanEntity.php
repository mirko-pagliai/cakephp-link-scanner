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

use Cake\Datasource\EntityInterface;
use Cake\Datasource\EntityTrait;
use Cake\Http\Client\Response;

/**
 * A `ScanEntity` represents a single result of a scan
 */
class ScanEntity implements EntityInterface
{
    use EntityTrait;

    /**
     * Initializes the internal properties of this entity out of the keys in an
     *  array
     * @param array $properties hash of properties to set in this entity
     */
    public function __construct(array $properties = [])
    {
        $this->set($properties);
    }

    /**
     * Magic method, is triggered when invoking inaccessible methods.
     *
     * It calls the same method name from the original `Response` class
     * @param string $name Method name
     * @param mixed $arguments Method arguments
     * @return mixed
     * @see \Cake\Http\Client\Response
     */
    public function __call($name, $arguments)
    {
        $response = new Response;
        if (method_exists($response, $name)) {
            $response = $response->withHeader('location', $this->get('location'))
                ->withStatus($this->get('code'));
            $name = [$response, $name];
        }

        return call_user_func_array($name, $arguments);
    }
}
