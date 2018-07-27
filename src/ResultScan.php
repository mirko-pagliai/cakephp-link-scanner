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

use Cake\Collection\Collection;
use Cake\ORM\Entity;
use Countable;
use IteratorAggregate;
use LogicException;
use Serializable;

/**
 * This object represents the results of a scan
 */
class ResultScan implements Countable, IteratorAggregate, Serializable
{
    /**
     * A `Collection` instance
     * @var \Cake\Collection\Collection
     */
    protected $collection;

    /**
     * Magic method, triggered when invoking inaccessible methods in an object
     *  context.
     *
     * It invokes the method from the `Collection` instance.
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return $this|mixed
     * @uses $collection
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->collection, $name], $arguments);
    }

    /**
     * Constructor
     * @param array $items Items
     * @return $this
     * @uses append()
     * @uses $collection
     */
    public function __construct(array $items = [])
    {
        //The collection is not created directly with `$items`, but by calling
        //  the `append()` method for each item.
        //  This allows checking the validity of the data
        $this->collection = new Collection([]);

        array_map([$this, 'append'], $items);

        return $this;
    }

    /**
     * Creates a new iterator from an `ArrayObject` instance
     * @return Collection
     * @uses $collection
     */
    public function getIterator()
    {
        return $this->collection;
    }

    /**
     * Appends an item
     * @param array|Entity $item Item to append as `Entity` or as array that
     *  will be transformed into an entity
     * @return $this
     * @throws LogicException
     * @uses $collection
     */
    public function append($item)
    {
        if (!$item instanceof Entity) {
            $item = new Entity($item);
        }

        if (!$item->has(['code', 'external', 'type', 'url'])) {
            throw new LogicException(__d('link-scanner', 'Missing data in the item to be appended'));
        }

        $existing = $this->collection->toArray();
        $items = [$item];

        if ($existing) {
            $items = array_merge($existing, $items);
        }

        $this->collection = new Collection($items);

        return $this;
    }

    /**
     * Counts the number of items
     * @return int
     * @uses $collection
     */
    public function count()
    {
        return count($this->collection->toArray());
    }

    /**
     * Returns a string representation of this object that can be used to
     *  reconstruct it
     * @return string
     * @uses $collection
     */
    public function serialize()
    {
        return serialize($this->collection->buffered());
    }

    /**
     * Unserializes the passed string and rebuilds the Collection instance
     * @param string $collection The serialized collection
     * @return void
     * @uses __construct()
     */
    public function unserialize($collection)
    {
        $this->__construct(safe_unserialize($collection)->toArray());
    }
}
