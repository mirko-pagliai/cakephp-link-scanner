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
use Cake\ORM\Entity;
use Cake\Routing\Router;
use Exception;
use InvalidArgumentException;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ResultScan;
use LogicException;

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
    protected $ResultScan;

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
     * Full base url
     * @var string|array|null
     */
    protected $fullBaseUrl = null;

    /**
     * Host name
     * @var string|array|null
     */
    protected $host = null;

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
     * Timeout in seconds for each request
     * @var int
     */
    protected $timeout = 30;

    /**
     * Construct
     * @param string|array|null $fullBaseUrl Full base url. If `null`, the
     *  `App.fullBaseUrl` value will be used
     * @return $this
     * @uses setFullBaseUrl()
     * @uses $Client
     * @uses $ResultScan
     */
    public function __construct($fullBaseUrl = null)
    {
        $this->Client = new Client;
        $this->ResultScan = new ResultScan;

        if (!$fullBaseUrl) {
            $fullBaseUrl = Configure::read('App.fullBaseUrl');
        }

        $this->setFullBaseUrl($fullBaseUrl);

        return $this;
    }

    /**
     * Magic method to get the properties value
     * @param string $name Property name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * Internal method to create a lock file
     * @return bool
     * @throws LogicException
     */
    protected function createLockFile()
    {
        if (!LINK_SCANNER_LOCK_FILE) {
            return true;
        }

        if (file_exists(LINK_SCANNER_LOCK_FILE)) {
            throw new LogicException(__d(
                'link-scanner',
                'The lock file `{0}` already exists. This means that a scan is already in progress. If not, remove it manually',
                LINK_SCANNER_LOCK_FILE
            ));
        }

        return file_put_contents(LINK_SCANNER_LOCK_FILE, null) !== false;
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

        $host = getHostnameFromUrl($url);

        //Url with the same host and relative url are not external
        return $host && strcasecmp($host, $this->host) !== 0;
    }

    /**
     * Exports scan results as serialized array.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.resultsExported`: will be triggered when the results have
     *  been exported.
     * @param string $filename Filename where to export
     * @return string
     * @throws LogicException
     * @uses $ResultScan
     * @uses $endTime
     * @uses $fullBaseUrl
     * @uses $host
     * @uses $maxDepth
     * @uses $startTime
     */
    public function export($filename = null)
    {
        if ($this->ResultScan->isEmpty()) {
            throw new LogicException(__d('link-scanner', 'There is no result to export. Perhaps the scan was not performed?'));
        }

        $filename = $filename ?: TMP . sprintf('results_%s_%s', $this->host, $this->startTime);

        try {
            $data = [
                'fullBaseUrl' => $this->fullBaseUrl,
                'maxDepth' => $this->maxDepth,
                'startTime' => $this->startTime,
                'endTime' => $this->endTime,
                'ResultScan' => $this->ResultScan,
            ];

            file_put_contents($filename, serialize($data));
        } catch (Exception $e) {
            throw new LogicException(__d('link-scanner', 'Failed to export results to file `{0}`', $filename));
        }

        $this->dispatchEvent('LinkScanner.resultsExported', [$filename]);

        return $filename;
    }

    /**
     * Imports scan results.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.resultsImported`: will be triggered when the results have
     *  been exported.
     * @param string $filename Filename from which to import
     * @return $this
     * @throws InternalErrorException
     * @uses $ResultScan
     * @uses $endTime
     * @uses $fullBaseUrl
     * @uses $maxDepth
     * @uses $startTime
     */
    public function import($filename)
    {
        try {
            $data = unserialize(file_get_contents($filename));

            $this->fullBaseUrl = $data['fullBaseUrl'];
            $this->maxDepth = $data['maxDepth'];
            $this->startTime = $data['startTime'];
            $this->endTime = $data['endTime'];
            $this->ResultScan = $data['ResultScan'];
        } catch (Exception $e) {
            throw new LogicException(__d('link-scanner', 'Failed to import results from file `{0}`', $filename));
        }

        $this->dispatchEvent('LinkScanner.resultsImported', [$filename]);

        return $this;
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
     *      scanning a single url, other links to be scanned are found;
     *  - `LinkScanner.responseNotHtml`: will be triggered when a single url is
     *      scanned and the response body does not contain html code;
     *  - `LinkScanner.responseNotOk`: will be triggered when a single url is
     *      scanned and the response is not ok.
     * @param string|array $url Url to scan
     * @return void
     * @uses $ResultScan
     * @uses $currentDepth
     * @uses $externalLinks
     * @uses $maxDepth
     * @uses getResponse()
     * @uses isExternalUrl()
     */
    protected function _scan($url)
    {
        $this->dispatchEvent('LinkScanner.beforeScanUrl', [$url]);

        $response = $this->getResponse($url);

        $item = new Entity;
        $item->code = $response->getStatusCode();
        $item->external = $this->isExternalUrl($url);
        $item->type = $response->getContentType();
        $item->url = is_string($url) ? $url : Router::url($url, true);

        $this->ResultScan->append($item);

        $this->dispatchEvent('LinkScanner.afterScanUrl', [$response]);

        //Returns, if the maximum scanning depth has been reached
        if ($this->maxDepth && $this->currentDepth >= $this->maxDepth) {
            return;
        }
        $this->currentDepth++;

        //Returns, if the response is not ok
        if (!$response->isOk()) {
            $this->dispatchEvent('LinkScanner.responseNotOk', [$url]);

            return;
        }

        //Returns, if the response body does not contain html code
        if (!$response->bodyIsHtml()) {
            $this->dispatchEvent('LinkScanner.responseNotHtml', [$url]);

            return;
        };

        //The links to be scanned are the difference between the links found in
        //  the body of the response and the already scanned links
        $linksToScan = array_diff(
            $response->extractLinksFromBody(),
            $this->ResultScan->extract('url')->toArray()
        );

        if (empty($linksToScan)) {
            return;
        }

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
     * @uses $ResultScan
     * @uses $endTime
     * @uses $fullBaseUrl
     * @uses $startTime
     * @uses _scan()
     * @uses createLockFile()
     * @uses reset()
     */
    public function scan()
    {
        $this->createLockFile();

        $this->reset();

        $this->startTime = time();

        $this->dispatchEvent('LinkScanner.scanStarted', [$this->startTime, $this->fullBaseUrl]);

        $this->_scan($this->fullBaseUrl);

        $this->endTime = time();

        //@codingStandardsIgnoreLine
        @unlink(LINK_SCANNER_LOCK_FILE);

        $this->dispatchEvent('LinkScanner.scanCompleted', [$this->endTime, $this->ResultScan]);

        return $this;
    }

    /**
     * Sets the full base url
     * @param string|array $fullBaseUrl Full base url
     * @return $this
     * @throws InvalidArgumentException
     * @uses $fullBaseUrl
     * @uses $host
     */
    public function setFullBaseUrl($fullBaseUrl)
    {
        if (is_string($fullBaseUrl) && !isUrl($fullBaseUrl)) {
            throw new InvalidArgumentException(__d('link-scanner', 'Invalid url `{0}`', $fullBaseUrl));
        }

        $this->host = null;

        if (is_string($fullBaseUrl)) {
            $fullBaseUrl = clearUrl($fullBaseUrl) . '/';
            $this->host = getHostnameFromUrl($fullBaseUrl);
        }

        $this->fullBaseUrl = $fullBaseUrl;

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
