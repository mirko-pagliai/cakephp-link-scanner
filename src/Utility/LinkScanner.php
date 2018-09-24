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

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventList;
use Cake\Filesystem\Folder;
use Cake\Http\Client;
use Exception;
use InvalidArgumentException;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ORM\ScanEntity;
use LinkScanner\ResultScan;
use RuntimeException;
use Serializable;

/**
 * A link scanner
 */
class LinkScanner implements Serializable
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

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
     * Default configuration
     * @var array
     */
    protected $_defaultConfig = [
        'maxDepth' => 0,
    ];

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
     * @param ResultScan $ResultScan Optional `ResultScan` instance
     * @param Client $Client Optional `Client` instance
     * @uses $Client
     * @uses $ResultScan
     * @uses setFullBaseUrl()
     */
    public function __construct($fullBaseUrl = null, ResultScan $ResultScan = null, Client $Client = null)
    {
        $this->Client = $Client ?: new Client(['redirect' => true]);
        $this->ResultScan = $ResultScan ?: new ResultScan;

        $this->setFullBaseUrl($fullBaseUrl ?: Configure::readOrFail('App.fullBaseUrl'));
    }

    /**
     * Magic method for reading data from inaccessible properties
     * @param string $name Property name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * Returns the string representation of the object
     * @return string
     * @uses $Client
     */
    public function serialize()
    {
        $properties = get_object_vars($this);

        //Unsets the event class and event manager. For the `Client` instance,
        //  it takes only configuration and cookies
        unset($properties['_eventClass'], $properties['_eventManager']);

        $properties['Client'] = $this->Client->getConfig() + ['cookieJar' => $this->Client->cookies()];

        return serialize($properties);
    }

    /**
     * Called during unserialization of the object
     * @param string $serialized The string representation of the object
     * @return void
     * @uses $Client
     */
    public function unserialize($serialized)
    {
        $properties = unserialize($serialized);

        //Resets the event list and the Client instance
        $this->getEventManager()->setEventList(new EventList);
        $this->Client = new Client($properties['Client']);
        unset($properties['Client']);

        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }
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
     * Performs a single GET request and returns the response as `ScanResponse`.
     *
     * The response will be cached, if that's ok and the cache is enabled.
     * @param string $url The url or path you want to request
     * @return ScanResponse
     * @uses $Client
     * @uses $fullBaseUrl
     */
    protected function getResponse($url)
    {
        $cacheKey = sprintf('response_%s', md5(serialize($url)));
        $response = Cache::read($cacheKey, LINK_SCANNER);

        if (!$response) {
            $response = new ScanResponse($this->Client->get($url), $this->fullBaseUrl);

            if ($response->isOk()) {
                Cache::write($cacheKey, $response, LINK_SCANNER);
            }
        }

        return $response;
    }

    /**
     * Exports scan results.
     *
     * The filename will be generated automatically, or you can indicate a
     *  relative or absolute path.
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.resultsExported`: will be triggered when the results have
     *  been exported.
     * @param string|null $filename Filename where to export
     * @return string
     * @see serialize()
     * @throws RuntimeException
     * @uses getResults()
     * @uses $hostname
     * @uses $startTime
     */
    public function export($filename = null)
    {
        if ($this->ResultScan->isEmpty()) {
            throw new RuntimeException(__d('link-scanner', 'There is no result to export. Perhaps the scan was not performed?'));
        }

        try {
            $filename = $filename ?: sprintf('results_%s_%s', $this->hostname, $this->startTime);
            $filename = Folder::isAbsolute($filename) ? $filename : LINK_SCANNER_TARGET . DS . $filename;
            file_put_contents($filename, serialize($this));
        } catch (Exception $e) {
            $message = preg_replace('/^file_put_contents\([\/\w\d:\-\\\\]+\): /', null, $e->getMessage());
            throw new RuntimeException(__d('link-scanner', 'Failed to export results to file `{0}` with message `{1}`', $filename, $message));
        }

        $this->dispatchEvent(LINK_SCANNER . '.resultsExported', [$filename]);

        return $filename;
    }

    /**
     * Imports scan results.
     *
     * You can indicate a relative or absolute path.
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.resultsImported`: will be triggered when the results have
     *  been exported.
     * @param string $filename Filename from which to import
     * @return object
     * @see unserialize()
     * @throws RuntimeException
     */
    public static function import($filename)
    {
        try {
            $filename = Folder::isAbsolute($filename) ? $filename : LINK_SCANNER_TARGET . DS . $filename;
            $instance = unserialize(file_get_contents($filename));
        } catch (Exception $e) {
            $message = preg_replace('/^file_get_contents\([\/\w\d:\-\\\\]+\): /', null, $e->getMessage());
            throw new RuntimeException(__d('link-scanner', 'Failed to import results from file `{0}` with message `{1}`', $filename, $message));
        }

        $instance->dispatchEvent(LINK_SCANNER . '.resultsImported', [$filename]);

        return $instance;
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
     * @param string|null $referer Referer of this url
     * @return void
     * @uses _scan()
     * @uses getResponse()
     * @uses getResults()
     * @uses $currentDepth
     * @uses $externalLinks
     * @uses $hostname
     */
    protected function _scan($url, $referer = null)
    {
        $this->dispatchEvent(LINK_SCANNER . '.beforeScanUrl', [$url]);

        try {
            $response = $this->getResponse($url);
        } catch (Exception $e) {
            return;
        }

        //Appends result
        $item = new ScanEntity(compact('referer', 'url'));
        $item->code = $response->getStatusCode();
        $item->external = is_external_url($url, $this->hostname);
        $item->type = $response->getContentType();
        $this->ResultScan->append($item);

        $this->dispatchEvent(LINK_SCANNER . '.afterScanUrl', [$response]);

        $this->currentDepth++;

        //Returns, if the maximum scanning depth has been reached
        if ($this->getConfig('maxDepth') && $this->currentDepth >= $this->getConfig('maxDepth')) {
            return;
        }

        //Returns, if the response is not ok
        if (!$response->isOk()) {
            $this->dispatchEvent(LINK_SCANNER . '.responseNotOk', [$url]);

            return;
        }

        //Returns, if the response body does not contain html code
        if (!$response->BodyParser->isHtml()) {
            $this->dispatchEvent(LINK_SCANNER . '.responseNotHtml', [$url]);

            return;
        };

        //The links to be scanned are the difference between the links found in
        //  the body of the response and the already scanned links
        $linksToScan = array_diff($response->BodyParser->extractLinks(), $this->ResultScan->getScannedUrl());

        foreach ($linksToScan as $link) {
            //Checks that the link has not already been scanned
            if (in_array($link, $this->ResultScan->getScannedUrl())) {
                continue;
            }

            //Skips external links
            if (is_external_url($link, $this->hostname)) {
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
     * @uses _scan()
     * @uses createLockFile()
     * @uses getResults()
     * @uses $endTime
     * @uses $fullBaseUrl
     * @uses $startTime
     */
    public function scan()
    {
        $this->createLockFile();

        $this->startTime = time();

        $this->dispatchEvent(LINK_SCANNER . '.scanStarted', [$this->startTime, $this->fullBaseUrl]);

        $this->_scan($this->fullBaseUrl);

        $this->endTime = time();

        safe_unlink(LINK_SCANNER_LOCK_FILE);

        $this->dispatchEvent(LINK_SCANNER . '.scanCompleted', [$this->startTime, $this->endTime, $this->ResultScan]);

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
}
