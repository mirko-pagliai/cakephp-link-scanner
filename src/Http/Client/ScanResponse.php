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
     * @uses $_response
     * @uses $fullBaseUrl
     */
    public function __construct($response, $fullBaseUrl)
    {
        $this->_response = $response;
        $this->fullBaseUrl = clearUrl($fullBaseUrl) . '/';
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
     * Internal method to extract all links from an HTML string
     * @return array
     * @uses $fullBaseUrl
     * @uses $tags
     * @uses body()
     */
    public function extractLinksFromBody()
    {
        $libxmlPreviousState = libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        $dom->loadHTML($this->body());

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

                if (substr($link, 0, 2) === '//') {
                    $link = 'http:' . $link;
                }

                $link = clearUrl($link);

                //Turns links as absolute
                if (!isUrl($link)) {
                    $link = $scheme . '://' . $host . '/' . $link;
                }

                $links[] = $link;
            }
        }

        return array_unique($links);
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