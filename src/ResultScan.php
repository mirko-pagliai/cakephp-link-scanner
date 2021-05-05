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

use Cake\Collection\Collection;
use Cake\Collection\CollectionInterface;
use LinkScanner\ScanEntity;
use Traversable;

/**
 * A `ResultScan` instance is a collection that contains a scan results
 */
class ResultScan extends Collection
{
    /**
     * Internal method to parse items.
     *
     * Ensures that each item is a `ScanEntity` and has all the properties it needs
     * @param iterable $items Items
     * @return array<\LinkScanner\ScanEntity> Parsed items
     */
    protected function parseItems(iterable $items): array
    {
        $items = $items instanceof Traversable ? $items->toArray() : $items;

        return array_map(function ($item): ScanEntity {
            return $item instanceof ScanEntity ? $item : new ScanEntity($item);
        }, $items);
    }

    /**
     * Constructor
     * @param iterable $items Items
     * @uses parseItems()
     */
    public function __construct(iterable $items = [])
    {
        parent::__construct($this->parseItems($items));
    }

    /**
     * Unserializes the passed string and rebuilds the Collection instance
     * @param string $collection The serialized collection
     * @return void
     */
    public function unserialize($collection): void
    {
        parent::__construct(unserialize($collection));
    }

    /**
     * Appends items.
     *
     * Returns a new `ResultScan` instance as the result of concatenating the
     *  list of elements in this collection with the passed list of elements
     * @param iterable $items Items
     * @return \Cake\Collection\CollectionInterface
     * @uses parseItems()
     */
    public function append($items): CollectionInterface
    {
        return new ResultScan(array_merge($this->buffered()->toArray(), $this->parseItems($items)));
    }

    /**
     * Prepends items.
     *
     * Returns a new `ResultScan` instance as the result of concatenating the
     *  passed list of elements with the list of elements in this collection
     * @param mixed $items Items
     * @return \Cake\Collection\CollectionInterface
     * @uses parseItems()
     */
    public function prepend($items): CollectionInterface
    {
        return new ResultScan(array_merge($this->parseItems($items), $this->buffered()->toArray()));
    }
}
