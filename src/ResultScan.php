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
use Countable;
use IteratorAggregate;
use LinkScanner\ORM\ScanEntity;
use LogicException;
use Serializable;

/**
 * This class contains the results of the scan.
 *
 * Summarily, it works like the `Collection` class, simulating all its methods.
 */
class ResultScan implements Countable, IteratorAggregate, Serializable
{
    /**
     * A `Collection` instance
     * @var \Cake\Collection\Collection
     */
    protected $Collection;

    /**
     * Constructor.
     *
     * The `Collection` instance is not created directly with `$items`, but by
     *  calling the `append()` method. This allows checking their validity
     * @param array $items Array of items
     * @uses append()
     */
    public function __construct(array $items = [])
    {
        $this->append($items);
    }

    /**
     * Magic method, triggered when invoking inaccessible methods in an object
     *  context.
     *
     * It invokes the method from the `Collection` instance.
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return $this|mixed
     * @uses $Collection
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->Collection, $name], $arguments);
    }

    /**
     * Appends an array of items
     * @param array $items Array of items
     * @return $this
     * @throws LogicException
     * @uses $Collection
     */
    public function append(array $items)
    {
        foreach ($items as $k => $item) {
            if (!$item instanceof ScanEntity) {
                $items[$k] = new ScanEntity($item);
            }

            is_true_or_fail($items[$k]->has(['code', 'external', 'type', 'url']), __d('link-scanner', 'Missing data in the item to be appended'), LogicException::class);
        }

        $items = $this->Collection && !$this->Collection->isEmpty() ? array_merge($this->Collection->toArray(), $items) : $items;
        $this->Collection = new Collection($items);

        return $this;
    }

    /**
     * Appends a single item
     * @param array|Entity $item Item to append as `ScanEntity` or as array that
     *  will be transformed into a `ScanEntity`
     * @return $this
     * @uses append()
     */
    public function appendItem($item)
    {
        return $this->append([$item]);
    }

    /**
     * Counts the number of items
     * @return int
     * @uses $Collection
     */
    public function count()
    {
        return $this->Collection->count();
    }

    /**
     * Creates a new iterator from an `ArrayObject` instance
     * @return \Cake\Collection\Collection
     * @uses $Collection
     */
    public function getIterator()
    {
        return $this->Collection;
    }

    /**
     * Get the already scanned links
     * @return array
     * @uses $Collection
     */
    public function getScannedUrl()
    {
        return $this->Collection->extract('url')->toArray();
    }

    /**
     * Returns a string representation of this object that can be used to
     *  reconstruct it
     * @return string
     * @uses $Collection
     */
    public function serialize()
    {
        return $this->Collection->serialize();
    }

    /**
     * Unserializes the passed string and rebuilds the Collection instance
     * @param string $collection The serialized collection
     * @return void
     * @uses $Collection
     */
    public function unserialize($collection)
    {
        $this->Collection = new Collection(unserialize($collection));
    }
}
