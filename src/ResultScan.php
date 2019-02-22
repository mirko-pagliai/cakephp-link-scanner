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
use LinkScanner\ScanEntity;
use Traversable;

/**
 * This class contains the results of the scan
 */
class ResultScan extends Collection
{
    /**
     * Internal method to parse items.
     *
     * Ensures that each item is a `ScanEntity` and has all the properties it needs
     * @param array|Traversable $items Array of items
     * @return array Parsed items
     * @throws PropertyNotExistsException
     */
    protected function parseItems($items)
    {
        $items = $items instanceof Traversable ? $items->toArray() : $items;

        return array_map(function ($item) {
            $item = $item instanceof ScanEntity ? $item : new ScanEntity($item);
            property_exists_or_fail($item, ['code', 'external', 'type', 'url']);

            return $item;
        }, $items);
    }

    /**
     * Constructor
     * @param array|Traversable $items Items
     * @uses parseItems()
     */
    public function __construct($items = [])
    {
        parent::__construct($this->parseItems($items));
    }

    /**
     * Appends items.
     *
     * Returns a new `ResultScan` instance as the result of concatenating the
     *  list of elements in this collection with the passed list of elements
     * @param array|Traversable $items Items
     * @return \LinkScanner\ResultScan
     * @uses parseItems()
     */
    public function append($items)
    {
        return new ResultScan(array_merge($this->buffered()->toArray(), $this->parseItems($items)));
    }

    /**
     * Prepends items.
     *
     * Returns a new `ResultScan` instance as the result of concatenating the
     *  passed list of elements with the list of elements in this collection
     * @param array|Traversable $items Items
     * @return \LinkScanner\ResultScan
     * @uses parseItems()
     */
    public function prepend($items)
    {
        return new ResultScan(array_merge($this->parseItems($items), $this->buffered()->toArray()));
    }
}
