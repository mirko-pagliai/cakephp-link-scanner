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
use DOMDocument;
use Serializable;
use Zend\Diactoros\Stream;

/**
 * A scan Response.
 *
 * This class simulates the `Response` class.
 */
class ScanResponse implements Serializable
{
    /**
     * @var \Cake\Http\Client\Response
     */
    public $Response;

    /**
     * Extracted links by the `extractLinksFromBody()` method.
     * This property works as a cache of values.
     * @var array|null
     */
    protected $extractedLinks = null;

    /**
     * Full base url
     * @see __construct()
     * @var string
     */
    protected $fullBaseUrl;

    /**
     * HTML tags to be scanned, because they can contain links to other
     *  resources. Tag name as key and attribute name as value
     * @var array
     */
    protected $tags = [
        'a' => 'href',
        'area' => 'href',
        'audio' => 'src',
        'embed' => 'src',
        'frame' => 'src',
        'iframe' => 'src',
        'img' => 'src',
        'link' => 'href',
        'script' => 'src',
        'source' => 'src',
        'track' => 'src',
        'video' => 'src',
    ];

    /**
     * Construct
     * @param \Cake\Http\Client\Response|\Cake\TestSuite\Stub\Response $response Original
     *  response object
     * @param string $fullBaseUrl Full base url
     * @uses $Response
     * @uses $fullBaseUrl
     */
    public function __construct($response, $fullBaseUrl)
    {
        $this->Response = $response;
        $this->fullBaseUrl = clean_url($fullBaseUrl, true);
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
        return call_user_func([$this->Response, $name], $arguments);
    }

    /**
     * Returns the string representation of the object
     * @return string
     * @uses $Response
     * @uses $extractedLinks
     * @uses $fullBaseUrl
     */
    public function serialize()
    {
        $body = (string)$this->Response->getBody();

        return serialize([$this->Response, $body, $this->extractedLinks, $this->fullBaseUrl]);
    }

    /**
     * Called during unserialization of the object
     * @param string $serialized String representation of object
     * @uses $Response
     * @uses $extractedLinks
     * @uses $fullBaseUrl
     */
    public function unserialize($serialized)
    {
        list($this->Response, $body, $this->extractedLinks, $this->fullBaseUrl) = unserialize($serialized);

        $stream = new Stream('php://memory', 'wb+');
        $stream->write($body);
        $stream->rewind();

        $this->Response = $this->Response->withBody($stream);
    }

    /**
     * Checks if the body contains html code
     * @return bool
     */
    public function bodyIsHtml()
    {
        $body = $this->getBody();

        return strcasecmp($body, strip_tags($body)) !== 0;
    }

    /**
     * Internal method to extract all links from an HTML string
     * @return array
     * @uses $extractedLinks
     * @uses $fullBaseUrl
     * @uses $tags
     * @uses body()
     */
    public function extractLinksFromBody()
    {
        if (!is_null($this->extractedLinks)) {
            return $this->extractedLinks;
        }

        $libxmlPreviousState = libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        $dom->loadHTML($this->getBody());

        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPreviousState);

        $links = [];
        $scheme = parse_url($this->fullBaseUrl, PHP_URL_SCHEME);
        $host = parse_url($this->fullBaseUrl, PHP_URL_HOST);

        foreach ($this->tags as $tag => $attribute) {
            foreach ($dom->getElementsByTagName($tag) as $element) {
                $link = $element->getAttribute($attribute);

                if (!$link) {
                    continue;
                }

                //Turns links as absolute
                if (substr($link, 0, 2) === '//') {
                    $link = $scheme . ':' . $link;
                } elseif (!is_url($link)) {
                    $link = $scheme . '://' . $host . '/' . ltrim($link, '/');
                }

                $links[] = clean_url($link, true);
            }
        }

        $this->extractedLinks = array_unique($links);

        return $this->extractedLinks;
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
