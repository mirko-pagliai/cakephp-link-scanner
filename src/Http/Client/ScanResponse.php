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
     * @var \Cake\Http\Client\Response
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
        $this->fullBaseUrl = clean_url($fullBaseUrl, true);
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
        return call_user_func_array([$this->Response, $name], $arguments);
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
        $body = (string)$this->Response->getBody();

        return serialize([$this->BodyParser, $this->Response, $body, $this->fullBaseUrl]);
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
        $contentType = $this->Response->getHeaderLine('content-type');

        //This removes an eventual charset
        return trim(first_value(explode(';', $contentType)));
    }

    /**
     * Checks if the response is ok
     * @return bool
     * @uses $Response
     */
    public function isOk()
    {
        $response = $this->Response;

        if (!method_exists($response, 'isOk')) {
            $response = (new Response)->withStatus($response->getStatusCode());
        }

        return $response->isOk();
    }
}
