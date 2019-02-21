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
use Tools\BodyParser;
use Zend\Diactoros\Stream;

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
    protected $Client;

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
        'cache' => true,
        'excludeLinks' => ['\{.*\}', 'javascript:'],
        'externalLinks' => true,
        'followRedirects' => false,
        'maxDepth' => 0,
        'lockFile' => true,
        'target' => TMP,
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
     * Lock file path
     * @var string
     */
    protected $lockFile = TMP . 'link_scanner_lock_file';

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
     * @param \Cake\Http\Client|null $Client A Client instance or null
     * @param \LinkScanner\ResultScan|null $ResultScan A Client instance or null
     * @uses $Client
     * @uses $ResultScan
     * @uses setFullBaseUrl()
     */
    public function __construct($fullBaseUrl = null, $Client = null, $ResultScan = null)
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
     * Internal method to create a lock file
     * @return bool
     * @throws RuntimeException
     * @uses $lockFile
     */
    protected function _createLockFile()
    {
        is_true_or_fail(!$this->getConfig('lockFile') || !file_exists($this->lockFile), __d(
            'link-scanner',
            'Lock file `{0}` already exists, maybe a scan is already in progress. If not, remove it manually',
            $this->lockFile
        ), RuntimeException::class);

        return $this->getConfig('lockFile') ? touch($this->lockFile) !== false : true;
    }

    /**
     * Performs a single GET request and returns a `ScanResponse` instance.
     *
     * The response will be cached, if that's ok and the cache is enabled.
     * @param string $url The url or path you want to request
     * @return Response
     * @uses $Client
     * @uses $alreadyScanned
     * @uses $fullBaseUrl
     */
    protected function _getResponse($url)
    {
        $this->alreadyScanned[] = $url;
        $cacheKey = sprintf('response_%s', md5(serialize($url)));

        $response = $this->getConfig('cache') ? Cache::read($cacheKey, 'LinkScanner') : null;

        if ($response && is_array($response)) {
            list($response, $body) = $response;

            $stream = new Stream('php://memory', 'wb+');
            $stream->write($body);
            $stream->rewind();
            $response = $response->withBody($stream);
        }

        if (!$response instanceof Response) {
            try {
                $response = $this->Client->get($url);

                if ($this->getConfig('cache') && ($response->isOk() || $response->isRedirect())) {
                    Cache::write($cacheKey, [$response, (string)$response->getBody()], 'LinkScanner');
                }
            } catch (Exception $e) {
                $response = (new Response)->withStatus(404);
            }
        }

        return $response;
    }

    /**
     * Internal method to perform a recursive scan.
     *
     * It recursively repeats the scan for all urls found that have not
     *  already been scanned.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.foundLinkToBeScanned`: will be triggered when other links
     *      to be scanned are found;
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

        $BodyParser = new BodyParser($response->getBody(), $url);

        //Returns, if the response body does not contain html code
        if (!$BodyParser->isHtml()) {
            return;
        }

        //Continues scanning for the links found
        $linksToBeScanned = array_filter($BodyParser->extractLinks(), [$this, 'canBeScanned']);
        foreach ($linksToBeScanned as $link) {
            //Skips, if the link has already been scanned
            if (in_array($link, $this->alreadyScanned)) {
                continue;
            }

            $this->dispatchEvent('LinkScanner.foundLinkToBeScanned', [$link]);

            //Single scan for external links, recursive scan for internal links
            call_user_func_array([$this, is_external_url($link, $this->hostname) ? '_singleScan' : __METHOD__], [$link, $url]);
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
     *      scanned;
     *  - `LinkScanner.foundRedirect`: will be triggered if a redirect is found.
     * @param string|array $url Url to scan
     * @param string|null $referer Referer of this url
     * @return ScanResponse|null
     * @uses _getResponse()
     * @uses canBeScanned()
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

        //Follows redirects
        if ($response->isRedirect() && $this->getConfig('followRedirects')) {
            $location = $response->getHeaderLine('location');
            if (!$this->canBeScanned($location)) {
                return null;
            }

            $this->dispatchEvent('LinkScanner.foundRedirect', [$location]);

            return call_user_func([$this, __METHOD__], $location);
        }

        $this->ResultScan = $this->ResultScan->appendItem(new ScanEntity([
            'code' => $response->getStatusCode(),
            'external' => is_external_url($url, $this->hostname),
            'location' => $response->getHeaderLine('Location'),
            'type' => $response->getHeaderLine('content-type'),
        ] + compact('url', 'referer')));

        return $response;
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
        if (!is_url($url) || in_array($url, $this->alreadyScanned) ||
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
            $filename = Folder::isAbsolute($filename) ? $filename : $this->getConfig('target') . DS . $filename;
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
            $filename = Folder::isAbsolute($filename) ? $filename : self::getConfig('target') . DS . $filename;
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
     * @uses $lockFile
     * @uses $startTime
     */
    public function scan()
    {
        $this->_createLockFile();

        $this->startTime = time();

        $this->dispatchEvent('LinkScanner.scanStarted', [$this->startTime, $this->fullBaseUrl]);

        $this->_recursiveScan($this->fullBaseUrl);

        $this->endTime = time();

        @unlink($this->lockFile);

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
