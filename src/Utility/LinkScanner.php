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
use Cake\Http\Client\Response;
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
    public $ResultScan;

    /**
     * Default configuration
     * @var array
     */
    protected $_defaultConfig = [
        'excludeLinks' => ['\{.*\}', 'javascript:'],
        'externalLinks' => true,
        'followRedirects' => false,
        'maxDepth' => 0,
    ];

    /**
     * Urls already scanned
     * @var array
     */
    protected $alreadyScanned = [];

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
     * @uses $Client
     * @uses $ResultScan
     * @uses setFullBaseUrl()
     */
    public function __construct($fullBaseUrl = null)
    {
        $this->Client = new Client(['redirect' => true]);
        $this->ResultScan = new ResultScan;

        $this->setFullBaseUrl($fullBaseUrl ?: Configure::readOrFail('App.fullBaseUrl'));
    }

    /**
     * Internal method to create a lock file
     * @return bool
     * @throws RuntimeException
     */
    protected function _createLockFile()
    {
        is_true_or_fail(!LINK_SCANNER_LOCK_FILE || !file_exists(LINK_SCANNER_LOCK_FILE), __d(
            'link-scanner',
            'The lock file `{0}` already exists. This means that a scan is already in progress. If not, remove it manually',
            LINK_SCANNER_LOCK_FILE
        ), RuntimeException::class);

        return LINK_SCANNER_LOCK_FILE ? touch(LINK_SCANNER_LOCK_FILE) !== false : true;
    }

    /**
     * Performs a single GET request and returns the response as `ScanResponse`.
     *
     * The response will be cached, if that's ok and the cache is enabled.
     * @param string $url The url or path you want to request
     * @return ScanResponse
     * @uses $Client
     * @uses $alreadyScanned
     * @uses $fullBaseUrl
     */
    protected function _getResponse($url)
    {
        $this->alreadyScanned[] = $url;
        $cacheExists = Cache::getConfig('LinkScanner');
        $cacheKey = sprintf('response_%s', md5(serialize($url)));

        $response = $cacheExists ? Cache::read($cacheKey, 'LinkScanner') : null;

        if (!$response instanceof ScanResponse) {
            try {
                $clientResponse = $this->Client->get($url);
            } catch (Exception $e) {
                $clientResponse = (new Response)->withStatus(404);
            }

            $response = new ScanResponse($clientResponse, $this->fullBaseUrl);
            if ($cacheExists && !$response->isError()) {
                Cache::write($cacheKey, $response, 'LinkScanner');
            }
        }

        return $response;
    }

    /**
     * Internal method to perform a recursive scan.
     *
     * It recursively repeats the scan for all the urls found that have not
     *  already been scanned.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.foundLinkToBeScanned`: will be triggered if, after
     *      scanning a single url, an other link to be scanned are found;
     *  - `LinkScanner.responseNotOk`: will be triggered when a single url is
     *      scanned and the response is not ok.
     * @param string|array $url Url to scan
     * @param string|null $referer Referer of this url
     * @return void
     * @uses _singleScan()
     * @uses canBeScanned()
     * @uses $alreadyScanned
     * @uses $currentDepth
     * @uses $hostname
     */
    protected function _recursiveScan($url, $referer = null)
    {
        $response = $this->_singleScan($url, $referer);

        if (!$response) {
            return;
        }

        //Returns, if the maximum scanning depth has been reached
        if ($this->getConfig('maxDepth') && ++$this->currentDepth >= $this->getConfig('maxDepth')) {
            return;
        }

        //Returns, if the response is not ok
        if (!$response->isOk()) {
            $this->dispatchEvent('LinkScanner.responseNotOk', [$url]);

            return;
        }

        //Returns, if the response body does not contain html code
        if (!$response->BodyParser->isHtml()) {
            return;
        }

        $linksToBeScanned = array_filter($response->BodyParser->extractLinks(), [$this, 'canBeScanned']);

        foreach ($linksToBeScanned as $link) {
            //Skips, if the link has already been scanned
            if (in_array($link, $this->alreadyScanned)) {
                continue;
            }

            $this->dispatchEvent('LinkScanner.foundLinkToBeScanned', [$link]);

            //Single scan for external links, recursive scan for internal links
            $methodToCall = is_external_url($link, $this->hostname) ? '_singleScan' : '_recursiveScan';
            $this->$methodToCall($link, $url);
        }
    }

    /**
     * Internal method to perform a single scan.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.beforeScanUrl`: will be triggered before a single url is
     *      scanned;
     *  - `LinkScanner.afterScanUrl`: will be triggered after a single url is
     *      scanned.
     * @param string|array $url Url to scan
     * @param string|null $referer Referer of this url
     * @return ScanResponse|null
     * @uses _getResponse()
     * @uses $ResultScan
     * @uses $hostname
     */
    protected function _singleScan($url, $referer = null)
    {
        if (!$this->canBeScanned($url)) {
            return null;
        }

        $this->dispatchEvent('LinkScanner.beforeScanUrl', [$url]);
        $response = $this->_getResponse($url);
        $this->dispatchEvent('LinkScanner.afterScanUrl', [$response]);

        //Recursive scan for redirects
        if ($this->getConfig('followRedirects')) {
            $location = $response->getHeaderLine('location');
            if ($response->isRedirect() && $location) {
                if (!$this->canBeScanned($location)) {
                    return null;
                }

                $this->dispatchEvent('LinkScanner.foundRedirect', [$location]);

                return $this->_singleScan($location);
            }
        }

        //Appends result
        $this->ResultScan->append(new ScanEntity([
            'code' => $response->getStatusCode(),
            'external' => is_external_url($url, $this->hostname),
            'location' => $response->getHeaderLine('Location'),
            'type' => $response->getContentType(),
        ] + compact('url', 'referer')));

        return $response;
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
     * Checks if an url can be scanned.
     *
     * Returns false if:
     *  - the url has already been scanned;
     *  - it's an external url and the external url scan has been disabled;
     *  - the url matches the url patterns to be excluded.
     * @param string $url Url to check
     * @return bool
     * @uses $alreadyScanned
     * @uses $hostname
     */
    protected function canBeScanned($url)
    {
        if (in_array($url, $this->alreadyScanned) ||
            (!$this->getConfig('externalLinks') && is_external_url($url, $this->hostname))) {
            return false;
        }

        $excludeLinks = $this->getConfig('excludeLinks');
        if ($excludeLinks && preg_match('/' . implode('|', (array)$excludeLinks) . '/', $url)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the string representation of the object
     * @return string
     * @uses $Client
     */
    public function serialize()
    {
        //Unsets the event class and event manager. For the `Client` instance,
        //  it takes only configuration and cookies
        $properties = get_object_vars($this);
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
        //Resets the event list and the Client instance
        $properties = unserialize($serialized);
        $this->getEventManager()->setEventList(new EventList);
        $this->Client = new Client($properties['Client']);
        unset($properties['Client']);

        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }
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
     * @uses $ResultScan
     * @uses $hostname
     * @uses $startTime
     */
    public function export($filename = null)
    {
        is_true_or_fail(!$this->ResultScan->isEmpty(), __d('link-scanner', 'There is no result to export. Perhaps the scan was not performed?'), RuntimeException::class);

        try {
            $filename = $filename ?: sprintf('results_%s_%s', $this->hostname, $this->startTime);
            $filename = Folder::isAbsolute($filename) ? $filename : LINK_SCANNER_TARGET . DS . $filename;
            file_put_contents($filename, serialize($this));
        } catch (Exception $e) {
            $message = preg_replace('/^file_put_contents\([\/\w\d:\-\\\\]+\): /', null, $e->getMessage());
            throw new RuntimeException(__d('link-scanner', 'Failed to export results to file `{0}` with message `{1}`', $filename, $message));
        }

        $this->dispatchEvent('LinkScanner.resultsExported', [$filename]);

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

        $instance->dispatchEvent('LinkScanner.resultsImported', [$filename]);

        return $instance;
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
     * Other events will be triggered by `_recursiveScan()` and `_singleScan()`
     *  methods.
     * @return $this
     * @uses _createLockFile()
     * @uses _recursiveScan()
     * @uses $ResultScan
     * @uses $endTime
     * @uses $fullBaseUrl
     * @uses $startTime
     */
    public function scan()
    {
        $this->_createLockFile();

        $this->startTime = time();

        $this->dispatchEvent('LinkScanner.scanStarted', [$this->startTime, $this->fullBaseUrl]);

        $this->_recursiveScan($this->fullBaseUrl);

        $this->endTime = time();

        @unlink(LINK_SCANNER_LOCK_FILE);

        $this->dispatchEvent('LinkScanner.scanCompleted', [$this->startTime, $this->endTime, $this->ResultScan]);

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
        is_true_or_fail(is_url($fullBaseUrl), __d('link-scanner', 'Invalid url `{0}`', $fullBaseUrl), InvalidArgumentException::class);
        $this->fullBaseUrl = clean_url($fullBaseUrl);
        $this->hostname = get_hostname_from_url($fullBaseUrl);

        return $this;
    }
}
