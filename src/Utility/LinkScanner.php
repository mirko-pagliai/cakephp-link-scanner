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
     * Full base url
     * @var string
     */
    protected $fullBaseUrl;

    /**
     * Host name
     * @var string
     */
    protected $host;

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
        $this->fullBaseUrl = clearUrl($fullBaseUrl);
        $this->host = parse_url($this->fullBaseUrl, PHP_URL_HOST);
    }

    /**
     * Internal method to extract all links from an HTML string
     * @param string $html HTML string
     * @return array
     * @uses $fullBaseUrl
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

                $link = clearUrl($link);

                //Turns links as absolute
                if (!isUrl($link)) {
                    $link = $this->fullBaseUrl . '/' . $link;
                }

                $links[] = $link;
            }
        }

        return array_values(array_unique($links));
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
     */
    public function get($url)
    {
        $response = $this->Client->get($url);

        $code = $response->getStatusCode();
        $links = [];
        $type = $response->getHeaderLine('content-type');

        if (statusCodeIsOk($code) && $this->isHtmlString($response->body())) {
            $links = $this->getLinksFromHtml($response->body());
        }

        return compact('code', 'links', 'type');
    }
}
