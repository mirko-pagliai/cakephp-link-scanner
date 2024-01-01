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

use BadMethodCallException;
use Cake\Http\Client\Response;
use LogicException;

/**
 * A `ScanEntity` represents a single result of a scan
 * @method bool isRedirect()
 * @method bool isSuccess()
 */
class ScanEntity extends Entity
{
    /**
     * @var \Cake\Http\Client\Response
     */
    protected Response $Response;

    /**
     * Initializes the internal properties
     * @param array $properties Properties to set
     * @throws \LogicException
     */
    public function __construct(array $properties = [])
    {
        foreach (['code', 'external', 'type', 'url'] as $name) {
            if (!array_key_exists($name, $properties)) {
                throw new LogicException('Key `' . $name . '` does not exist');
            }
        }

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
     * @thrown \BadMethodCallException
     */
    public function __call(string $name, $arguments)
    {
        if (method_exists(Response::class, $name)) {
            $Response = (new Response())
                ->withHeader('location', $this->get('location'))
                ->withStatus($this->get('code'));
            /** @var callable $name */
            $name = [$Response, $name];
        }

        if (!is_callable($name)) {
            throw new BadMethodCallException('Method `' . implode(':', (array)$name) . '()` does not exist');
        }

        return call_user_func_array($name, $arguments);
    }
}
