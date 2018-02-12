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

/**
 * This object represents the results of a scan
 */
class ResultScan extends Collection
{
    /**
     * Constructor. You can provide an array or any traversable object
     * @param array|Traversable $items Items
     */
    public function __construct($items = [])
    {
        parent::__construct($items);
    }

    /**
     * Returns a new `ResultScan` object as the result of concatenating the
     *  list of elements in this collection with the passed list of elements
     * @param array|Traversable $items Items list
     * @return \LinkScanner\ResultScan
     */
    public function append($items)
    {
        return new self(array_merge($this->toArray(), $items));
    }

    /**
     * Counts the number of items
     * @return int
     */
    public function count()
    {
        return count($this->toArray());
    }

    /**
     * Returns another `ResultScan` object after modifying each of the values in
     *  this one using the provided callable.
     *
     * Each time the callback is executed it will receive the value of the
     *  element in the current iteration, the key of the element and this
     *  collection as arguments, in that order.
     * @param callable $c The method that will receive each of the elements and
     *  returns the new value for the key that is being iterated
     * @return \LinkScanner\ResultScan
     */
    public function map(callable $c)
    {
        return new self(parent::map($c)->toArray());
    }
}
