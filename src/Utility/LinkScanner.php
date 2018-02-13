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
use DOMDocument;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ResultScan;
use LinkScanner\Utility\ResultExporter;

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
     * Instance of `ResultScan`. This contains the results of the scan
     * @var \LinkScanner\ResultScan
     */
    protected $ResultScan;

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
     * External links found during the scan
     * @var array
     */
    protected $externalLinks = [];

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
     * Start time
     * @var int
     */
    protected $startTime = 0;

    /**
     * Timeout for requests
     * @var int
     */
    protected $timeout = 30;

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
     * @uses $ResultScan
     * @uses $fullBaseUrl
     * @uses $host
     */
    public function __construct($fullBaseUrl = null)
    {
        if (!$fullBaseUrl) {
            $fullBaseUrl = Configure::read('App.fullBaseUrl');
        }

        $this->Client = new Client;
        $this->ResultScan = new ResultScan;
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
     * @param string|array $url Url
     * @return bool
     * @uses $host
     */
    protected function isExternalUrl($url)
    {
        if (is_array($url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        //Url with the same host and relative url are not external
        return !empty($host) && strcasecmp($host, $this->host) !== 0;
    }

    /**
     * Performs a single GET request and returns the response as `ScanResponse`
     * @param string $url The url or path you want to request
     * @return array Array with `code`, `external`, `type`, `url`, `links` keys
     * @uses $Client
     * @uses $timeout
     * @uses getLinksFromHtml()
     * @uses isExternalUrl()
     */

    /**
     * Performs a single GET request and returns the response as `ScanResponse`
     * @param string $url The url or path you want to request
     * @return ScanResponse
     * @uses $timeout
     */
    protected function getResponse($url)
    {
        return new ScanResponse($this->Client->get($url, [], ['redirect' => true, 'timeout' => $this->timeout]));
    }

    /**
     * Resets some properties whose initial value may have changed during the
     *  last scan
     * @return $this
     * @uses $ResultScan
     * @uses $currentDepth
     * @uses $elapsedTime
     * @uses $externalLinks
     * @uses $startTime
     */
    public function reset()
    {
        $this->ResultScan = new ResultScan;
        $this->externalLinks = [];
        $this->currentDepth = $this->elapsedTime = $this->startTime = 0;

        return $this;
    }

    /**
     * Exports the scan results.
     *
     * Valid formats: `array` (serialized), `html` and `xml`
     * @param string|null $exportAs Format
     * @param string $filename Filename where to export
     * @return string|bool Filename where to export or `false` on failure
     * @see ResultExporter
     * @uses $ResultScan
     * @uses $elapsedTime
     * @uses $fullBaseUrl
     * @uses $host
     * @uses $maxDepth
     * @uses $startTime
     */
    public function export($exportAs = 'array', $filename = null)
    {
        $startTime = (new Time($this->startTime))->i18nFormat('yyyy-MM-dd HH:mm:ss');

        if (!$filename) {
            $filename = TMP . sprintf('results_%s_%s', $this->host, $startTime);

            if ($exportAs === 'html') {
                $filename .= '.html';
            } elseif ($exportAs === 'xml') {
                $filename .= '.xml';
            }
        }

        $ResultExporter = new ResultExporter($this->fullBaseUrl, $this->maxDepth, $startTime, $this->elapsedTime, $this->ResultScan);

        return call_user_func([$ResultExporter, 'as' . ucfirst($exportAs)], $filename);
    }

    /**
     * Internal method to scan.
     *
     * This method takes an url as a parameter. It scans that URL and
     *  recursively it repeats the scan for all the url found that have not
     *  already been scanned.
     * @param string $url Url to scan
     * @return void
     * @uses $ResultScan
     * @uses $currentDepth
     * @uses $externalLinks
     * @uses $maxDepth
     * @uses getResponse()
     * @uses getLinksFromHtml()
     * @uses isExternalUrl()
     */
    protected function _scan($url)
    {
        $response = $this->getResponse($url);

        $this->ResultScan = $this->ResultScan->append([[
            'code' => $response->getStatusCode(),
            'external' => $this->isExternalUrl($url),
            'type' => $response->getContentType(),
            'url' => $url,
        ]]);

        //Returns, if the maximum scanning depth has been reached
        if ($this->maxDepth && $this->currentDepth >= $this->maxDepth) {
            return;
        }
        $this->currentDepth++;

        $linksToScan = [];

        //The links to be scanned are the difference between the links found in
        //  the body of the response and the already scanned links
        if ($response->isOk() && $response->bodyIsHtml()) {
            $linksToScan = array_diff(
                $this->getLinksFromHtml($response->body()),
                $this->ResultScan->extract('url')->toArray()
            );
        }

        foreach ($linksToScan as $link) {
            //Skips external links
            if ($this->isExternalUrl($link)) {
                $this->externalLinks[] = $link;

                continue;
            }

            $this->_scan($link);
        }
    }

    /**
     * Performs a complete scan
     * @return $this
     * @uses $elapsedTime
     * @uses $fullBaseUrl
     * @uses $startTime
     * @uses _scan()
     * @uses reset()
     */
    public function scan()
    {
        $this->reset();

        $this->startTime = time();

        $this->_scan($this->fullBaseUrl);

        $this->elapsedTime = time() - $this->startTime;

        return $this;
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

    /**
     * Sets the timeout for each request of the scan
     * @param int $timeout Timeout for each request
     * @return $this
     * @uses $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }
}
