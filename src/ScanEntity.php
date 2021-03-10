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

use Cake\Http\Client\Response;
use Tools\Entity;
use Tools\Exceptionist;

/**
 * A `ScanEntity` represents a single result of a scan
 */
class ScanEntity extends Entity
{
    /**
     * A `Response` object
     * @var \Cake\Http\Client\Response
     */
    protected $Response;

    /**
     * Initializes the internal properties
     * @param array<string, int|string|bool|null> $properties Properties to set
     * @throws \Tools\Exception\KeyNotExistsException
     */
    public function __construct(array $properties = [])
    {
        Exceptionist::arrayKeyExists(['code', 'external', 'type', 'url'], $properties);
        parent::__construct($properties);
    }

    /**
     * Magic method, is triggered when invoking inaccessible methods.
     *
     * It calls the same method name from the original `Response` class.
     * @param string $name Method name
     * @param mixed $arguments Method arguments
     * @return mixed
     * @see \Cake\Http\Client\Response
     */
    public function __call(string $name, $arguments)
    {
        if (method_exists(Response::class, $name)) {
            $response = (new Response())
                ->withHeader('location', $this->get('location'))
                ->withStatus($this->get('code'));
            $name = [$response, $name];
        }

        return call_user_func_array($name, $arguments);
    }
}
