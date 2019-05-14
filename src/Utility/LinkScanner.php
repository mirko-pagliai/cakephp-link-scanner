<?php
declare(strict_types=1);
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
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventList;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Exception;
use InvalidArgumentException;
use LinkScanner\ResultScan;
use LinkScanner\ScanEntity;
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
    protected $ResultScan;

    /**
     * Default configuration
     * @var array
     */
    protected $_defaultConfig = [
        'cache' => true,
        'excludeLinks' => '/[\{\}+]/',
        'externalLinks' => true,
        'followRedirects' => false,
        'fullBaseUrl' => null,
        'maxDepth' => 0,
        'lockFile' => true,
        'target' => TMP . 'cakephp-link-scanner',
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
     * Host name
     * @var string|array|null
     */
    protected $hostname = null;

    /**
     * Lock file path
     * @var string
     */
    protected $lockFile = TMP . 'cakephp-link-scanner' . DS . 'link_scanner_lock_file';

    /**
     * Start time
     * @var int
     */
    protected $startTime = 0;

    /**
     * Construct
     * @param \Cake\Http\Client|null $Client A Client instance or null
     * @param \LinkScanner\ResultScan|null $ResultScan A Client instance or null
     * @uses $Client
     * @uses $ResultScan
     */
    public function __construct($Client = null, ?ResultScan $ResultScan = null)
    {
        $this->Client = $Client ?: new Client(['redirect' => true]);
        $this->ResultScan = $ResultScan ?: new ResultScan();

        if (file_exists(CONFIG . 'link_scanner.php')) {
            $config = (new PhpConfig())->read('link_scanner');
            if (isset($config['LinkScanner'])) {
                $this->setConfig($config['LinkScanner']);
            }
        }
    }

    /**
     * Magic method for reading data from inaccessible properties
     * @param string $name Property name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->$name;
    }

    /**
     * Internal method to create a lock file
     * @return bool
     * @throws \RuntimeException
     * @uses $lockFile
     */
    protected function _createLockFile(): bool
    {
        is_true_or_fail(!$this->getConfig('lockFile') || !file_exists($this->lockFile), __d(
            'link-scanner',
            'Lock file `{0}` already exists, maybe a scan is already in progress. If not, remove it manually',
            $this->lockFile
        ), RuntimeException::class);

        return $this->getConfig('lockFile') ? new File($this->lockFile, true) !== false : true;
    }

    /**
     * Performs a single GET request and returns a `ScanResponse` instance.
     *
     * The response will be cached, if that's ok and the cache is enabled.
     * @param string $url The url or path you want to request
     * @return \Cake\Http\Client\Response
     * @uses $Client
     * @uses $alreadyScanned
     * @uses $fullBaseUrl
     */
    protected function _getResponse(string $url): Response
    {
        $this->alreadyScanned[] = $url;
        $cacheKey = sprintf('response_%s', md5(serialize($url)));

        $response = $this->getConfig('cache') ? Cache::read($cacheKey, 'LinkScanner') : null;

        if ($response && is_array($response)) {
            [$response, $body] = $response;

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
                $response = (new Response())->withStatus(404);
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
     * @param string $url Url to scan
     * @param string|null $referer Referer of this url
     * @return void
     * @uses _singleScan()
     * @uses canBeScanned()
     * @uses $alreadyScanned
     * @uses $currentDepth
     * @uses $hostname
     */
    protected function _recursiveScan(string $url, ?string $referer = null): void
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

        //Continues scanning for the links found
        foreach ((new BodyParser($response->getBody(), $url))->extractLinks() as $link) {
            if (!$this->canBeScanned($link)) {
                continue;
            }
            $this->dispatchEvent('LinkScanner.foundLinkToBeScanned', [$link]);

            //Single scan for external links, recursive scan for internal links
            $method = is_external_url($link, $this->hostname) ? '_singleScan' : __METHOD__;
            call_user_func_array([$this, $method], [$link, $url]);
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
     * @param string $url Url to scan
     * @param string|null $referer Referer of this url
     * @return \Cake\Http\Client\Response|null
     * @uses _getResponse()
     * @uses canBeScanned()
     * @uses $ResultScan
     * @uses $hostname
     */
    protected function _singleScan(string $url, ?string $referer = null): ?Response
    {
        $url = clean_url($url, true, true);
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
    protected function canBeScanned(string $url): bool
    {
        if (!is_url($url) || in_array($url, $this->alreadyScanned) ||
            (!$this->getConfig('externalLinks') && is_external_url($url, $this->hostname))) {
            return false;
        }

        foreach ((array)$this->getConfig('excludeLinks') as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the string representation of the object
     * @return string
     * @uses $Client
     */
    public function serialize(): string
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
    public function unserialize($serialized): void
    {
        //Resets the event list and the Client instance
        $properties = unserialize($serialized);
        $this->getEventManager()->setEventList(new EventList());
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
     * @throws \RuntimeException
     * @uses $ResultScan
     * @uses $hostname
     * @uses $startTime
     */
    public function export(?string $filename = null): string
    {
        is_true_or_fail(
            !$this->ResultScan->isEmpty(),
            __d('link-scanner', 'There is no result to export. Perhaps the scan was not performed?'),
            RuntimeException::class
        );

        $filename = $filename ?: sprintf('results_%s_%s', $this->hostname, $this->startTime);
        if (!Folder::isAbsolute($filename)) {
            $filename = Folder::slashTerm($this->getConfig('target')) . $filename;
        }
        (new File($filename, true))->write(serialize($this));
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
     * @return $this
     * @see unserialize()
     * @throws \RuntimeException
     */
    public static function import(string $filename)
    {
        try {
            if (!Folder::isAbsolute($filename)) {
                $filename = Folder::slashTerm(self::getConfig('target')) . $filename;
            }
            $instance = unserialize(file_get_contents($filename));
        } catch (Exception $e) {
            $message = preg_replace('/^file_get_contents\([\/\w\d:\-\\\\]+\): /', null, $e->getMessage());
            throw new RuntimeException(__d(
                'link-scanner',
                'Failed to import results from file `{0}` with message `{1}`',
                $filename,
                $message
            ));
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
     * @throws \InvalidArgumentException
     * @uses _createLockFile()
     * @uses _recursiveScan()
     * @uses $ResultScan
     * @uses $endTime
     * @uses $hostname
     * @uses $lockFile
     * @uses $startTime
     */
    public function scan()
    {
        //Sets the full base url
        $fullBaseUrl = $this->getConfig('fullBaseUrl', Configure::read('App.fullBaseUrl') ?: 'http://localhost');
        is_true_or_fail(
            is_url($fullBaseUrl),
            __d('link-scanner', 'Invalid full base url `{0}`', $fullBaseUrl),
            InvalidArgumentException::class
        );
        $this->hostname = get_hostname_from_url($fullBaseUrl);

        $this->_createLockFile();
        $this->startTime = time();

        $maxNestingLevel = ini_set('xdebug.max_nesting_level', '-1');

        try {
            $this->dispatchEvent('LinkScanner.scanStarted', [$this->startTime, $fullBaseUrl]);
            $this->_recursiveScan($fullBaseUrl);
            $this->endTime = time();
            $this->dispatchEvent('LinkScanner.scanCompleted', [$this->startTime, $this->endTime, $this->ResultScan]);
        } finally {
            ini_set('xdebug.max_nesting_level', (string)$maxNestingLevel);
            @unlink($this->lockFile);
        }

        return $this;
    }
}
