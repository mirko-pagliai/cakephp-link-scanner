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
namespace LinkScanner\Http\Client;

use Cake\Http\Client\Response;

/**
 * A scan Response.
 *
 * This class simulates the methods of `Response` the class.
 */
class ScanResponse
{
    /**
     * @var \Cake\Http\Client\Response|\Cake\TestSuite\Stub\Response
     */
    protected $_response;

    /**
     * Construct
     * @param \Cake\Http\Client\Response|\Cake\TestSuite\Stub\Response $response Original
     *  response object
     * @uses $_response
     */
    public function __construct($response)
    {
        $this->_response = $response;
    }

    /**
     * Magic method, is triggered when invoking inaccessible methods. It calls
     *  the same method name from the original response object
     * @param string $name Method name
     * @param mixed $arguments Method arguments
     * @return mixed
     * @uses $_response
     */
    public function __call($name, $arguments)
    {
        return call_user_func([$this->_response, $name], $arguments);
    }

    /**
     * Gets the response body.
     *
     * By passing in a $parser callable, you can get the decoded response
     *  content back.
     * @param callable|null $parser The callback to use to decode the response
     *  body
     * @return mixed The response body
     * @uses $_response
     */
    public function body($parser = null)
    {
        return $this->_response->body($parser);
    }

    /**
     * Checks if the body contains html code
     * @return bool
     * @uses body()
     */
    public function bodyIsHtml()
    {
        $body = $this->body();

        return strcasecmp($body, strip_tags($body)) !== 0;
    }

    /**
     * Gets the content type from the request header
     * @return string
     * @uses $_response
     */
    public function getContentType()
    {
        return $this->_response->getHeaderLine('content-type');
    }

    /**
     * Checks if the response is ok
     * @return bool
     * @uses $_response
     */
    public function isOk()
    {
        if (method_exists($this->_response, 'isOk')) {
            return $this->_response->isOk();
        }

        $response = new Response;
        $response = $response->withStatus($this->_response->getStatusCode());

        return $response->isOk();
    }
}
