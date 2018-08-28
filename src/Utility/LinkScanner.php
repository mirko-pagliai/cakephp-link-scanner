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
use Exception;
use InvalidArgumentException;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ResultScan;
use RuntimeException;

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
    protected $hostname = null;

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
     * Construct.
     *
     * The full base url can be a string or an array of parameters as for the
     *  `Router::url()` method.
     * If `null` the `App.fullBaseUrl` value will be used.
     * @param string|array|null $fullBaseUrl Full base url
     * @uses setFullBaseUrl()
     * @uses $Client
     * @uses $ResultScan
     */
    public function __construct($fullBaseUrl = null)
    {
        $this->Client = new Client(['redirect' => true]);
        $this->ResultScan = new ResultScan;

        $this->setFullBaseUrl($fullBaseUrl ?: Configure::readOrFail('App.fullBaseUrl'));
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
     * @throws RuntimeException
     */
    protected function createLockFile()
    {
        if (LINK_SCANNER_LOCK_FILE && file_exists(LINK_SCANNER_LOCK_FILE)) {
            throw new RuntimeException(__d(
                'link-scanner',
                'The lock file `{0}` already exists. This means that a scan is already in progress. If not, remove it manually',
                LINK_SCANNER_LOCK_FILE
            ));
        }

        return LINK_SCANNER_LOCK_FILE ? touch(LINK_SCANNER_LOCK_FILE) !== false : true;
    }

    /**
     * Performs a single GET request and returns the response as `ScanResponse`
     * @param string $url The url or path you want to request
     * @return ScanResponse
     * @uses $Client
     * @uses $fullBaseUrl
     */
    protected function getResponse($url)
    {
        return new ScanResponse($this->Client->get($url), $this->fullBaseUrl);
    }

    /**
     * Internal method to get the already scanned links
     * @return array
     * @uses $ResultScan
     */
    protected function getScannedLinks()
    {
        return $this->ResultScan->extract('url')->toArray();
    }

    /**
     * Internal method to check if a link is external
     * @param string $link Link to check
     * @return bool
     * @uses $hostname
     */
    protected function isExternalLink($link)
    {
        return is_external_url($link, $this->hostname);
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
     * @throws RuntimeException
     * @uses $ResultScan
     * @uses $endTime
     * @uses $fullBaseUrl
     * @uses $hostname
     * @uses $maxDepth
     * @uses $startTime
     */
    public function export($filename = null)
    {
        if ($this->ResultScan->isEmpty()) {
            throw new RuntimeException(__d('link-scanner', 'There is no result to export. Perhaps the scan was not performed?'));
        }

        $filename = $filename ?: TMP . sprintf('results_%s_%s', $this->hostname, $this->startTime);

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
            throw new RuntimeException(__d('link-scanner', 'Failed to export results to file `{0}`', $filename));
        }

        $this->dispatchEvent(LINK_SCANNER . '.resultsExported', [$filename]);

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
     */
    public function import($filename)
    {
        try {
            $data = unserialize(file_get_contents($filename));

            foreach ($data as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        } catch (Exception $e) {
            throw new RuntimeException(__d('link-scanner', 'Failed to import results from file `{0}`', $filename));
        }

        $this->dispatchEvent(LINK_SCANNER . '.resultsImported', [$filename]);

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
     *  - `LinkScanner.foundLinkToBeScanned`: will be triggered if, after
     *      scanning a single url, an other link to be scanned are found;
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
     * @uses _scan()
     * @uses getResponse()
     * @uses getScannedLinks()
     * @uses isExternalLink()
     */
    protected function _scan($url, $referer = false)
    {
        $this->dispatchEvent(LINK_SCANNER . '.beforeScanUrl', [$url]);

        try {
            $response = $this->getResponse($url);
        } catch (Exception $e) {
            return;
        }

        //Appends result
        $item = new Entity(compact('referer', 'url'));
        $item->code = $response->getStatusCode();
        $item->external = $this->isExternalLink($url);
        $item->type = $response->getContentType();
        $this->ResultScan->append($item);

        $this->dispatchEvent(LINK_SCANNER . '.afterScanUrl', [$response]);

        $this->currentDepth++;

        //Returns, if the maximum scanning depth has been reached
        if ($this->maxDepth && $this->currentDepth >= $this->maxDepth) {
            return;
        }

        //Returns, if the response is not ok
        if (!$response->isOk()) {
            $this->dispatchEvent(LINK_SCANNER . '.responseNotOk', [$url]);

            return;
        }

        //Returns, if the response body does not contain html code
        if (!$response->bodyIsHtml()) {
            $this->dispatchEvent(LINK_SCANNER . '.responseNotHtml', [$url]);

            return;
        };

        //The links to be scanned are the difference between the links found in
        //  the body of the response and the already scanned links
        $linksToScan = array_diff($response->extractLinksFromBody(), $this->getScannedLinks());

        foreach ($linksToScan as $link) {
            //Checks that the link has not already been scanned
            if (in_array($link, $this->getScannedLinks())) {
                continue;
            }

            //Skips external links
            if ($this->isExternalLink($link)) {
                $this->externalLinks[] = $link;

                continue;
            }

            $this->dispatchEvent(LINK_SCANNER . '.foundLinkToBeScanned', [$link]);

            $this->_scan($link, $url);
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

        $this->dispatchEvent(LINK_SCANNER . '.scanStarted', [$this->startTime, $this->fullBaseUrl]);

        $this->_scan($this->fullBaseUrl);

        $this->endTime = time();

        safe_unlink(LINK_SCANNER_LOCK_FILE);

        $this->dispatchEvent(LINK_SCANNER . '.scanCompleted', [$this->endTime, $this->ResultScan]);

        return $this;
    }

    /**
     * Sets the full base url and, consequently, the hostname
     * @param string $fullBaseUrl Full base url
     * @return $this
     * @throws InvalidArgumentException
     * @uses $fullBaseUrl
     * @uses $hostname
     */
    public function setFullBaseUrl($fullBaseUrl)
    {
        if (!is_string($fullBaseUrl) || !is_url($fullBaseUrl)) {
            throw new InvalidArgumentException(__d('link-scanner', 'Invalid url `{0}`', $fullBaseUrl));
        }

        $this->fullBaseUrl = clean_url($fullBaseUrl, true);
        $this->hostname = get_hostname_from_url($fullBaseUrl);

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
     * @uses $Client
     */
    public function setTimeout($timeout)
    {
        $this->Client->setConfig(compact('timeout'));

        return $this;
    }
}
