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
namespace LinkScanner;

use ArrayAccess;

/**
 * An Entity class.
 *
 * It exposes the methods for retrieving and storing properties associated.
 * @template EntityPropertyName as string
 * @template EntityPropertyValue as mixed
 * @template EntityProperties as array<EntityPropertyName, EntityPropertyValue>
 */
abstract class Entity implements ArrayAccess
{
    /**
     * Properties
     * @var EntityProperties
     */
    protected array $properties;

    /**
     * Initializes the internal properties
     * @param EntityProperties $properties Properties to set
     */
    public function __construct(array $properties = [])
    {
        $this->properties = $properties;
    }

    /**
     * Called by `var_dump()` when dumping the object to get the properties that should be shown
     * @return array
     */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /**
     * Magic method for reading data from inaccessible properties
     * @param EntityPropertyName $property Property name
     * @return EntityPropertyValue
     */
    public function __get(string $property)
    {
        return $this->get($property);
    }

    /**
     * Checks if a property exists.
     *
     * This method also returns `true` for properties with empty, `false` or `null` value. Instead, use `hasValue()` to
     *  check the value as well.
     * @param EntityPropertyName $property Property name
     * @return bool
     */
    public function has(string $property): bool
    {
        return array_key_exists($property, $this->properties);
    }

    /**
     * Checks if a property exists and has a value
     * @param EntityPropertyName $property Property name
     * @return bool
     * @since 1.5.8
     */
    public function hasValue(string $property): bool
    {
        return (bool)$this->get($property);
    }

    /**
     * Magic method for reading data from inaccessible properties
     * @param EntityPropertyName $property Property name
     * @param mixed $default Default value if the property does not exist
     * @return mixed Property value
     */
    public function get(string $property, $default = null)
    {
        return $this->has($property) ? $this->properties[$property] : $default;
    }

    /**
     * Checks if a property does not exist or is empty
     * @param EntityPropertyName $property Property name
     * @return bool
     * @since 1.5.8
     */
    public function isEmpty(string $property): bool
    {
        return empty($this->get($property));
    }

    /**
     * Implements `isset($entity);`
     * @param EntityPropertyName $offset The offset to check
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->properties[$offset]);
    }

    /**
     * Implements `$entity[$offset];`
     * @param EntityPropertyName $offset The offset to get
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->properties[$offset];
    }

    /**
     * Implements `$entity[$offset] = $value;`
     * @param EntityPropertyName $offset The offset to set
     * @param EntityPropertyValue $value The value to set.
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->properties[$offset] = $value;
    }

    /**
     * Implements `unset($result[$offset]);`
     * @param EntityPropertyName $offset The offset to remove
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->properties[$offset]);
    }

    /**
     * Sets a property inside this entity.
     *
     * You can also pass an array as first argument containing names and values of multiple properties.
     * @param EntityPropertyName|array<EntityPropertyName, EntityPropertyValue> $property The name of property to set or a
     *  list of properties with their respective values
     * @param EntityPropertyValue $value The value to set to the property
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function set($property, $value = null)
    {
        $this->properties = array_merge($this->properties, is_array($property) ? $property : [$property => $value]);

        return $this;
    }

    /**
     * Returns an array with all the properties that have been set to this entity
     * @return array
     */
    public function toArray(): array
    {
        $properties = $this->properties;

        foreach ($properties as $name => $value) {
            if ($value instanceof Entity) {
                $properties[$name] = $value->toArray();
            }
        }

        return $properties;
    }
}
