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
use Serializable;
use Tools\BodyParser;
use Zend\Diactoros\Stream;

/**
 * A scan Response.
 *
 * This class simulates the `Response` class.
 */
class ScanResponse implements Serializable
{
    /**
     * @var \LinkScanner\Utility\BodyParser
     */
    public $BodyParser;

    /**
     * @var \Cake\Http\Client\Response|\Cake\TestSuite\Stub\Response
     */
    protected $Response;

    /**
     * Full base url
     * @var string
     */
    protected $fullBaseUrl;

    /**
     * Construct
     * @param \Cake\Http\Client\Response|\Cake\TestSuite\Stub\Response $response Original
     *  response object
     * @param string $fullBaseUrl Full base url
     * @uses $BodyParser
     * @uses $Response
     * @uses $fullBaseUrl
     */
    public function __construct($response, $fullBaseUrl)
    {
        $this->fullBaseUrl = clean_url($fullBaseUrl);
        $this->BodyParser = new BodyParser($response->getBody(), $this->fullBaseUrl);
        $this->Response = $response;
    }

    /**
     * Magic method, is triggered when invoking inaccessible methods. It calls
     *  the same method name from the original `Response` class
     * @param string $name Method name
     * @param mixed $arguments Method arguments
     * @return mixed
     * @see Cake\Http\Client\Response
     * @uses $Response
     */
    public function __call($name, $arguments)
    {
        $response = $this->Response;

        //This provides some method (for example, `isOk()` and `isRedirect()`),
        //  if the original `Response` method does not provide them
        if (!method_exists($response, $name) && !$response instanceof Response) {
            $response = (new Response)
                ->withHeader('location', $this->Response->getHeaderLine('location'))
                ->withStatus($this->Response->getStatusCode());
        }

        return call_user_func_array([$response, $name], $arguments);
    }

    /**
     * Returns the string representation of the object
     * @return string
     * @uses $BodyParser
     * @uses $Response
     * @uses $fullBaseUrl
     */
    public function serialize()
    {
        return serialize([$this->BodyParser, $this->Response, (string)$this->Response->getBody(), $this->fullBaseUrl]);
    }

    /**
     * Called during unserialization of the object
     * @param string $serialized String representation of object
     * @return void
     * @uses $BodyParser
     * @uses $Response
     * @uses $fullBaseUrl
     */
    public function unserialize($serialized)
    {
        list($this->BodyParser, $this->Response, $body, $this->fullBaseUrl) = unserialize($serialized);

        $stream = new Stream('php://memory', 'wb+');
        $stream->write($body);
        $stream->rewind();
        $this->Response = $this->Response->withBody($stream);
    }

    /**
     * Gets the content type from the request header
     * @return string
     * @uses $Response
     */
    public function getContentType()
    {
        //This removes an eventual charset
        return trim(array_value_first(explode(';', $this->Response->getHeaderLine('content-type'))));
    }

    /**
     * Checks if the response is error
     * @return bool
     */
    public function isError()
    {
        return !$this->isOk() && !$this->isRedirect();
    }
}
