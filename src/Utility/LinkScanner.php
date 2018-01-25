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
namespace LinkScanner\Utility;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\I18n\Time;
use Cake\Utility\Xml;
use DOMDocument;

/**
 * A link scanner
 */
class LinkScanner
{
    /**
     * Instance of `Client`
     * @var \Cake\Http\Client
     */
    public $Client;

    /**
     * Links that have already been scanned
     * @var array
     */
    protected $alreadyScanned = [];

    /**
     * Current scan depth level
     * @var int
     */
    protected $currentDepth = 0;

    /**
     * Elapsed time
     * @var int
     */
    protected $elapsedTime = 0;

    /**
     * Full base url
     * @see __construct()
     * @var string
     */
    protected $fullBaseUrl;

    /**
     * Host name
     * @var string
     */
    protected $host;

    /**
     * Maximum depth of the scan
     * @see setMaxDepth()
     * @var int
     */
    protected $maxDepth = 0;

    /**
     * Map of results
     * @var array
     */
    protected $map = [];

    /**
     * Start time
     * @var int
     */
    protected $startTime = 0;

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
     * @param string $fullBaseUrl Full base url. If `null`, the value from the
     *  configuration `App.fullBaseUrl` will be used
     * @uses $Client
     * @uses $fullBaseUrl
     * @uses $host
     */
    public function __construct($fullBaseUrl = null)
    {
        if (!$fullBaseUrl) {
            $fullBaseUrl = Configure::read('App.fullBaseUrl');
        }

        $this->Client = new Client;
        $this->fullBaseUrl = clearUrl($fullBaseUrl) . '/';
        $this->host = parse_url($this->fullBaseUrl, PHP_URL_HOST);
    }

    /**
     * Internal method to extract all links from an HTML string
     * @param string $html HTML string
     * @return array
     * @uses $fullBaseUrl
     * @uses $host
     * @uses $tags
     */
    protected function getLinksFromHtml($html)
    {
        $libxmlPreviousState = libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        $dom->loadHTML($html);

        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPreviousState);

        $links = [];

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
                    $link = parse_url($this->fullBaseUrl, PHP_URL_SCHEME) .
                        '://' . $this->host . '/' . $link;
                }

                $links[] = $link;
            }
        }

        return array_unique($links);
    }

    /**
     * Checks if an url is external
     * @param string $url Url
     * @return bool
     * @uses $host
     */
    protected function isExternalUrl($url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        //Url with the same host and relative url are not external
        return !empty($host) && strcasecmp($host, $this->host) !== 0;
    }

    /**
     * Checks if a string cointais html code
     * @param string $string String
     * @return bool
     */
    protected function isHtmlString($string)
    {
        return strcasecmp($string, strip_tags($string)) !== 0;
    }

    /**
     * Checks if a `Response` is ok
     * @param Response $response A `Response` object
     * @return bool
     */
    protected function responseIsOk($response)
    {
        return $response->isOk();
    }

    /**
     * Performs a single GET request.
     *
     * Returns an array that contains:
     *  - status code;
     *  - other links possibly contained;
     *  - content type.
     * @param string $url The url or path you want to request
     * @return array
     * @uses $Client
     * @uses getLinksFromHtml()
     * @uses isHtmlString()
     * @uses responseIsOk()
     */
    public function get($url)
    {
        $response = $this->Client->get($url, [], ['redirect' => true]);

        $links = [];

        if ($this->responseIsOk($response) && $this->isHtmlString($response->body())) {
            $links = $this->getLinksFromHtml($response->body());
        }

        return array_merge([
            'code' => $response->getStatusCode(),
            'type' => $response->getHeaderLine('content-type'),
        ], compact('links'));
    }

    /**
     * Sets the maximum depth of the scan
     * @param int $maxDepth Maximum depth of the scan
     * @return $this
     * @uses $maxDepth
     */
    public function setMaxDepth($maxDepth)
    {
        $this->maxDepth = $maxDepth;

        return $this;
    }
}
