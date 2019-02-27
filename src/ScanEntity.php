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

use ArrayAccess;
use Cake\Http\Client\Response;

/**
 * A `ScanEntity` represents a single result of a scan
 */
class ScanEntity implements ArrayAccess
{
    /**
     * Properties
     * @var array
     */
    protected $properties;

    /**
     * Initializes the internal properties
     * @param array $properties Properties to set
     * @uses $properties
     */
    public function __construct(array $properties = [])
    {
        $this->properties = $properties;
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
            $response = $response->withHeader('location', $this->location)
                ->withStatus($this->code);
            $name = [$response, $name];
        }

        return call_user_func_array($name, $arguments);
    }

    /**
     * Called by `var_dump()` when dumping the object to get the properties that
     *  should be shown
     * @return array
     * @uses $properties
     */
    public function __debugInfo()
    {
        return $this->properties;
    }

    /**
     * Magic method for reading data from inaccessible properties
     * @param string $name Property name
     * @return mixed Property value or `null` if the property does not exist
     * @uses has()
     * @uses $properties
     */
    public function __get($name)
    {
        return $this->has($name) ? $this->properties[$name] : null;
    }

    /**
     * Returs true if the entity has a property
     * @param string $name Property name
     * @return bool
     * @uses $properties
     */
    public function has($name)
    {
        return array_key_exists($name, $this->properties);
    }

    /**
     * Implements `isset($entity);`
     * @param mixed $offset The offset to check
     * @return bool
     * @uses has()
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * Implements `$entity[$offset];`
     * @param mixed $offset The offset to get
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Implements `$entity[$offset] = $value;`
     * @param mixed $offset The offset to set
     * @param mixed $value The value to set.
     * @return bool
     * @uses $properties
     */
    public function offsetSet($offset, $value)
    {
        $this->properties[$offset] = $value;

        return true;
    }

    /**
     * Implements `unset($result[$offset]);`
     * @param mixed $offset The offset to remove
     * @return bool `true` if the offset has been removed, `false` if the
     *  property does not exist
     * @uses has()
     * @uses $properties
     */
    public function offsetUnset($offset)
    {
        $exists = $this->has($offset);
        unset($this->properties[$offset]);

        return $exists;
    }
}
