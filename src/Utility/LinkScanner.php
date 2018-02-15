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
use Cake\Event\EventDispatcherTrait;
use Cake\Http\Client;
use Cake\I18n\Time;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ResultScan;
use LinkScanner\Utility\ResultExporter;

/**
 * A link scanner
 */
class LinkScanner
{
    use EventDispatcherTrait;

    /**
     * Instance of `Client`
     * @var \Cake\Http\Client
     */
    public $Client;

    /**
     * Instance of `ResultScan`. This contains the results of the scan
     * @var \LinkScanner\ResultScan
     */
    public $ResultScan;

    /**
     * Current scan depth level
     * @var int
     */
    protected $currentDepth = 0;

    /**
     * End time
     * @var int
     */
    protected $endTime;

    /**
     * External links found during the scan
     * @var array
     */
    protected $externalLinks = [];

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
     * Construct
     * @param string $fullBaseUrl Full base url. If `null`, the value from the
     *  configuration `App.fullBaseUrl` will be used
     * @return $this
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

        return $this;
    }

    /**
     * Internal method to append a `ScanResponse`, with url, to the `ResultScan`
     * @param string $url Url
     * @param ScanResponse $response A `ScanResponse` instance
     * @return void
     */
    protected function appendToResultsScan($url, ScanResponse $response)
    {
        $this->ResultScan = $this->ResultScan->append([[
            'code' => $response->getStatusCode(),
            'external' => $this->isExternalUrl($url),
            'type' => $response->getContentType(),
            'url' => $url,
        ]]);
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
        return $host && strcasecmp($host, $this->host) !== 0;
    }

    /**
     * Performs a single GET request and returns the response as `ScanResponse`
     * @param string $url The url or path you want to request
     * @return ScanResponse
     * @uses $Client
     * @uses $fullBaseUrl
     * @uses $timeout
     */
    protected function getResponse($url)
    {
        return new ScanResponse(
            $this->Client->get($url, [], ['redirect' => true, 'timeout' => $this->timeout]),
            $this->fullBaseUrl
        );
    }

    /**
     * Resets some properties whose initial value may have changed during the
     *  last scan
     * @return \self
     * @uses $fullBaseUrl
     */
    public function reset()
    {
        return new self($this->fullBaseUrl);
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
     * @uses $endTime
     * @uses $fullBaseUrl
     * @uses $host
     * @uses $maxDepth
     * @uses $startTime
     */
    public function export($exportAs = 'array', $filename = null)
    {
        $startTime = (new Time($this->startTime))->i18nFormat('yyyy-MM-dd HH:mm:ss');
        $endTime = (new Time($this->endTime))->i18nFormat('yyyy-MM-dd HH:mm:ss');

        if (!$filename) {
            $filename = TMP . sprintf('results_%s_%s', $this->host, $startTime);

            if ($exportAs === 'html') {
                $filename .= '.html';
            } elseif ($exportAs === 'xml') {
                $filename .= '.xml';
            }
        }

        $ResultExporter = new ResultExporter($this->fullBaseUrl, $this->maxDepth, $startTime, $endTime, $this->ResultScan);

        return call_user_func([$ResultExporter, 'as' . ucfirst($exportAs)], $filename);
    }

    /**
     * Internal method to scan.
     *
     * This method takes an url as a parameter. It scans that URL and
     *  recursively it repeats the scan for all the url found that have not
     *  already been scanned.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.beforeScanUrl`: will be triggered before a single url is
     *      scanned;
     *  - `LinkScanner.afterScanUrl`: will be triggered after a single url is
     *      scanned;
     *  - `LinkScanner.foundLinksToBeScanned`: will be triggered if, after
     *      scanning a single url, other links to be scanned are found.
     * @param string $url Url to scan
     * @return void
     * @uses $ResultScan
     * @uses $currentDepth
     * @uses $externalLinks
     * @uses $maxDepth
     * @uses appendToResultsScan()
     * @uses getResponse()
     * @uses isExternalUrl()
     */
    protected function _scan($url)
    {
        $this->dispatchEvent('LinkScanner.beforeScanUrl', [$url]);

        $response = $this->getResponse($url);

        $this->appendToResultsScan($url, $response);

        $this->dispatchEvent('LinkScanner.afterScanUrl', [$response]);

        //Returns, if the maximum scanning depth has been reached
        if ($this->maxDepth && $this->currentDepth >= $this->maxDepth) {
            return;
        }
        $this->currentDepth++;

        //Returns, if the response is not ok or if there are no other links to
        //  be scanned
        if (!$response->isOk() || !$response->bodyIsHtml()) {
            return;
        }

        //The links to be scanned are the difference between the links found in
        //  the body of the response and the already scanned links
        $linksToScan = array_diff(
            $response->extractLinksFromBody(),
            $this->ResultScan->extract('url')->toArray()
        );

        $this->dispatchEvent('LinkScanner.foundLinksToBeScanned', [$linksToScan]);

        foreach ($linksToScan as $url) {
            //Skips external links
            if ($this->isExternalUrl($url)) {
                $this->externalLinks[] = $url;

                continue;
            }

            $this->_scan($url);
        }
    }

    /**
     * Performs a complete scan.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.scanStarted`: will be triggered before the scan starts;
     *  - `LinkScanner.scanCompleted`: will be triggered after the scan is
     *      finished.
     *
     * Other events will be triggered by the `_scan()` method.
     * @return $this
     * @uses $endTime
     * @uses $fullBaseUrl
     * @uses $startTime
     * @uses _scan()
     * @uses reset()
     */
    public function scan()
    {
        $this->reset();

        $this->startTime = time();

        $this->dispatchEvent('LinkScanner.scanStarted', [$this->startTime, $this->fullBaseUrl]);

        $this->_scan($this->fullBaseUrl);

        $this->endTime = time();

        $this->dispatchEvent('LinkScanner.scanCompleted', [$this->endTime, $this->ResultScan]);

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
