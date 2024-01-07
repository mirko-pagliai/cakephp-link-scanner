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
     * @param \Cake\Collection\CollectionInterface|iterable $items Items
     * @return array<\LinkScanner\ScanEntity> Parsed items
     */
    protected function parseItems(iterable $items): array
    {
        if ($items instanceof CollectionInterface) {
            $items = $items->toArray();
        } elseif ($items instanceof Traversable) {
            $items = iterator_to_array($items);
        }

        return array_map(fn($item) => $item instanceof ScanEntity ? $item : new ScanEntity($item), $items);
    }

    /**
     * Constructor
     * @param \Cake\Collection\CollectionInterface|iterable $items Items
     */
    public function __construct(CollectionInterface|iterable $items = [])
    {
        parent::__construct($this->parseItems($items));
    }

    /**
     * Appends items.
     *
     * Returns a new `ResultScan` instance as the result of concatenating the list of elements.
     * @param iterable $items Items
     * @return \Cake\Collection\CollectionInterface
     */
    public function append(iterable $items): CollectionInterface
    {
        return new ResultScan(array_merge($this->buffered()->toArray(), $this->parseItems($items)));
    }

    /**
     * Prepends items.
     *
     * Returns a new `ResultScan` instance as the result of concatenating the passed list of elements.
     * @param mixed $items Items
     * @return \Cake\Collection\CollectionInterface
     */
    public function prepend(mixed $items): CollectionInterface
    {
        return new ResultScan(array_merge($this->parseItems($items), $this->buffered()->toArray()));
    }
}
