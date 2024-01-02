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
use Cake\Collection\CollectionInterface;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventList;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Exception;
use Laminas\Diactoros\Stream;
use LinkScanner\ResultScan;
use LinkScanner\ScanEntity;
use LogicException;
use PHPUnit\Framework\Exception as PHPUnitException;
use Serializable;
use Tools\Filesystem;

/**
 * A link scanner
 * @method \Cake\Event\EventManager getEventManager()
 */
class LinkScanner implements Serializable
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    /**
     * @var \Cake\Http\Client
     */
    public Client $Client;

    /**
     * Instance of `ResultScan`. This contains the results of the scan
     * @var \Cake\Collection\CollectionInterface
     */
    protected CollectionInterface $ResultScan;

    /**
     * Default configuration
     * @var array
     */
    protected array $_defaultConfig = [
        'cache' => true,
        'excludeLinks' => '/[\{\}+]/',
        'exportOnlyBadResults' => false,
        'externalLinks' => true,
        'followRedirects' => false,
        'fullBaseUrl' => null,
        'maxDepth' => 0,
        'lockFile' => true,
        'target' => LINK_SCANNER_TMP,
    ];

    /**
     * Urls already scanned
     * @var string[]
     */
    protected array $alreadyScanned = [];

    /**
     * Current scan depth level
     * @var int
     */
    protected int $currentDepth = 0;

    /**
     * End time
     * @var int
     */
    protected int $endTime;

    /**
     * Host name
     * @var string
     */
    protected string $hostname;

    /**
     * Lock file path
     * @var string
     */
    protected string $lockFile = LINK_SCANNER_TMP . 'link_scanner_lock_file';

    /**
     * Start time
     * @var int
     */
    protected int $startTime = 0;

    /**
     * Construct.
     *
     * Creates `Client` and `ResultScan` instances and loads a possible configuration file.
     * @param \Cake\Http\Client|null $Client A Client instance
     * @param \LinkScanner\ResultScan|null $ResultScan A ResultScan instance
     */
    public function __construct(?Client $Client = null, ?ResultScan $ResultScan = null)
    {
        $this->Client = $Client ?: new Client(['redirect' => true]);
        $this->ResultScan = $ResultScan ?: new ResultScan();

        if (file_exists(CONFIG . 'link_scanner.php')) {
            $config = (new PhpConfig())->read('link_scanner');
            $this->setConfig($config['LinkScanner'] ?? []);
        }
    }

    /**
     * Magic method for reading inaccessible properties
     * @param string $name Property name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->{$name};
    }

    /**
     * Internal method to check if an url can be scanned.
     *
     * Returns `false` if:
     *  - the url has already been scanned;
     *  - it's an external url and the external url scan has been disabled;
     *  - the url matches the url patterns to be excluded.
     * @param string $url Url to check
     * @return bool
     */
    protected function _canBeScanned(string $url): bool
    {
        if (!is_url($url) || in_array($url, $this->alreadyScanned) || (!$this->getConfig('externalLinks') && is_external_url($url, $this->hostname))) {
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
     * Internal method to create a lock file
     * @return bool
     * @throws \LogicException
     */
    protected function _createLockFile(): bool
    {
        if ($this->getConfig('lockFile') && file_exists($this->lockFile)) {
            throw new LogicException(
                __d('link-scanner', 'Lock file `{0}` already exists, maybe a scan is already in progress. If not, remove it manually', $this->lockFile)
            );
        }

        return $this->getConfig('lockFile') && Filesystem::instance()->createFile($this->lockFile);
    }

    /**
     * Internal method to get the absolute path for a filename
     * @param string $filename Filename
     * @return string
     * @since 1.0.7
     */
    protected function _getAbsolutePath(string $filename): string
    {
        return Filesystem::instance()->makePathAbsolute($filename, $this->getConfig('target'));
    }

    /**
     * Internal method to perform a single GET request and return a `Response` instance.
     *
     * The response will be cached, if the cache is enabled and if the response is "ok" or a redirect.
     * @param string $url The url or path you want to request
     * @return \Cake\Http\Client\Response
     */
    protected function _getResponse(string $url): Response
    {
        $this->alreadyScanned[] = $url;
        $cacheKey = 'response_' . md5(serialize($url));

        //Tries to get the response from the cache
        $Response = $this->getConfig('cache') ? Cache::read($cacheKey, 'LinkScanner') : null;
        if ($Response && is_array($Response)) {
            [$Response, $body] = $Response;

            $Stream = new Stream('php://memory', 'wb+');
            $Stream->write($body);
            $Stream->rewind();

            return $Response->withBody($Stream);
        }

        try {
            $Response = $this->Client->get($url);

            if ($this->getConfig('cache') && ($Response->isOk() || $Response->isRedirect())) {
                Cache::write($cacheKey, [$Response, (string)$Response->getBody()], 'LinkScanner');
            }
        } catch (PHPUnitException $e) {
            throw $e;
        } catch (Exception $e) {
            $Response = (new Response())->withStatus(404);
        }

        return $Response;
    }

    /**
     * Internal method to perform a recursive scan.
     *
     * It recursively scans all urls found that have not already been scanned and until the maximum depth is reached.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.foundLinkToBeScanned`: will be triggered when other links to be scanned are found;
     *  - `LinkScanner.responseNotOk`: will be triggered when a single url is scanned and the response is not ok.
     * @param string $url Url to scan
     * @param string $referer Referer of this url
     * @return void
     */
    protected function _recursiveScan(string $url, string $referer = ''): void
    {
        $Response = $this->_singleScan($url, $referer);
        if (!$Response) {
            return;
        }

        //Returns, if the maximum scanning depth has been reached
        if ($this->getConfig('maxDepth') && ++$this->currentDepth >= $this->getConfig('maxDepth')) {
            return;
        }

        //Returns, if the response is not ok
        if (!$Response->isOk()) {
            $this->dispatchEvent('LinkScanner.responseNotOk', [$url]);

            return;
        }

        //Continues scanning for the links found
        foreach ((new BodyParser($Response->getBody(), $url))->extractLinks() as $link) {
            if (!$this->_canBeScanned($link)) {
                continue;
            }
            $this->dispatchEvent('LinkScanner.foundLinkToBeScanned', [$link]);

            //Performs single scan for external links and recursive scan for internal links
            $method = is_external_url($link, $this->hostname) ? '_singleScan' : '_recursiveScan';
            $this->{$method}($link, $url);
        }
    }

    /**
     * Internal method to perform a single scan.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.beforeScanUrl`: will be triggered before a single url is scanned;
     *  - `LinkScanner.afterScanUrl`: will be triggered after a single url is scanned;
     *  - `LinkScanner.foundRedirect`: will be triggered if a redirect is found.
     * @param string $url Url to scan
     * @param string $referer Referer of this url
     * @return \Cake\Http\Client\Response|null
     */
    protected function _singleScan(string $url, string $referer = ''): ?Response
    {
        $url = clean_url($url, true, true);
        if (!$this->_canBeScanned($url)) {
            return null;
        }

        $this->dispatchEvent('LinkScanner.beforeScanUrl', [$url]);
        $Response = $this->_getResponse($url);
        $location = $Response->getHeaderLine('location');
        $this->dispatchEvent('LinkScanner.afterScanUrl', [$Response]);

        //Follows redirects
        if ($Response->isRedirect() && $this->getConfig('followRedirects')) {
            if (!$this->_canBeScanned($location)) {
                return null;
            }

            $this->dispatchEvent('LinkScanner.foundRedirect', [$location]);

            return $this->_singleScan($location);
        }

        //Creates a `ScanEntity` instance with the result and appends it to the
        //  `ResultScan` instance
        $this->ResultScan = $this->ResultScan->appendItem(new ScanEntity([
            'code' => $Response->getStatusCode(),
            'external' => is_external_url($url, $this->hostname),
            'type' => $Response->getHeaderLine('content-type'),
        ] + compact('location', 'url', 'referer')));

        return $Response;
    }

    /**
     * Returns the string representation of the object
     * @return string
     */
    public function serialize(): string
    {
        //Unsets the event class and event manager. For the `Client` instance, it takes only configuration and cookies
        $properties = get_object_vars($this);
        unset($properties['_eventClass'], $properties['_eventManager']);
        $properties['Client'] = $this->Client->getConfig() + ['cookieJar' => $this->Client->cookies()];

        return serialize($properties);
    }

    /**
     * Called during un-serialization of the object
     * @param string $data The string representation of the object
     * @return void
     */
    public function unserialize($data): void
    {
        //Resets the event list and the Client instance
        $properties = unserialize($data);
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
     * The filename will be generated automatically, or you can indicate a relative or absolute path.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.resultsExported`: will be triggered when the results have been exported.
     * @param string|null $filename Filename where to export
     * @return string
     * @see serialize()
     * @throws \LogicException
     */
    public function export(?string $filename = null): string
    {
        if ($this->ResultScan->isEmpty()) {
            throw new LogicException(__d('link-scanner', 'There is no result to export. Perhaps the scan was not performed?'));
        }

        if ($this->getConfig('exportOnlyBadResults')) {
            $this->ResultScan = new ResultScan($this->ResultScan->filter(fn(ScanEntity $item): bool => $item->get('code') >= 400));
        }

        $filename = $this->_getAbsolutePath($filename ?: sprintf('results_%s_%s', $this->hostname, $this->startTime));
        Filesystem::instance()->createFile($filename, serialize($this));
        $this->dispatchEvent('LinkScanner.resultsExported', [$filename]);

        return $filename;
    }

    /**
     * Imports scan results.
     *
     * You can indicate a relative or absolute path.
     *
     * ### Events
     * This method will trigger some events:
     *  - `LinkScanner.resultsImported`: will be triggered when the results have been exported.
     * @param string $filename Filename from which to import
     * @return $this
     * @throws \LogicException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function import(string $filename)
    {
        $filename = $this->_getAbsolutePath($filename);

        try {
            $instance = unserialize(file_get_contents($filename) ?: '');
        } catch (Exception $e) {
            $message = preg_replace('/file_get_contents\([^)]+\):\s+/', '', $e->getMessage()) ?: '';
            throw new LogicException(
                __d('link-scanner', 'Failed to import results from file `{0}` with message `{1}`', $filename, lcfirst($message))
            );
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
     *  - `LinkScanner.scanCompleted`: will be triggered after the scan is finished.
     *
     * Other events will be triggered by `_recursiveScan()` and `_singleScan()` methods.
     * @return $this
     * @throws \LogicException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function scan()
    {
        //Sets the full base url
        $fullBaseUrl = $this->getConfig('fullBaseUrl', Configure::read('App.fullBaseUrl') ?: 'http://localhost');
        if (!is_url($fullBaseUrl)) {
            throw new LogicException(__d('link-scanner', 'Invalid full base url `{0}`', $fullBaseUrl));
        }
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
