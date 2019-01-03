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
namespace LinkScanner\Event;

use Cake\Cache\Cache;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Event\Event;
use Cake\I18n\Time;
use LinkScanner\Event\LinkScannerEventListenerInterface;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ResultScan;
use LinkScanner\Command\LinkScannerCommand;

/**
 * Event listener for the `LinkScannerCommand` class.
 *
 * This class provided methods to be performed as events by `LinkScannerCommand`.
 */
class LinkScannerCommandEventListener implements LinkScannerEventListenerInterface
{
    /**
     * @var \Cake\Console\Arguments
     */
    protected $args;

    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected $io;

    /**
     * Construct
     * @param Arguments $args The command arguments
     * @param ConsoleIo $io The console io
     * @uses $args
     * @uses $io
     */
    public function __construct(Arguments $args, ConsoleIo $io)
    {
        $this->args = $args;
        $this->io = $io;
    }

    /**
     * Returns a list of events this object is implementing. When the class is
     *  registered in an event manager, each individual method will be
     *  associated with the respective event
     * @return array
     */
    public function implementedEvents()
    {
        $events = [
            'afterScanUrl',
            'beforeScanUrl',
            'foundLinkToBeScanned',
            'foundRedirect',
            'resultsExported',
            'scanCompleted',
            'scanStarted',
        ];

        return array_combine(array_map(function ($event) {
            return LINK_SCANNER . '.' . $event;
        }, $events), $events);
    }

    /**
     * `LinkScanner.afterScanUrl` event
     * @param Event $event An `Event` instance
     * @param ScanResponse $response A `ScanResponse` instance
     * @return bool
     * @uses $args
     * @uses $io
     */
    public function afterScanUrl(Event $event, ScanResponse $response)
    {
        if (!$this->args->getOption('verbose')) {
            return true;
        }

        if ($response->isOk()) {
            $this->io->success(__d('link-scanner', 'OK'));
        } else {
            call_user_func([$this->io, $response->isError() ? 'err' : 'warn'], (string)$response->getStatusCode());
        }

        return true;
    }

    /**
     * `LinkScanner.beforeScanUrl` event
     * @param Event $event An `Event` instance
     * @param string $url Url
     * @return bool
     * @uses $io
     */
    public function beforeScanUrl(Event $event, $url)
    {
        $this->io->verbose(__d('link-scanner', 'Checking {0} ...', $url), 0);

        return true;
    }

    /**
     * `LinkScanner.foundLinkToBeScanned` event
     * @param Event $event An `Event` instance
     * @param string $link Link
     * @return bool
     * @uses $io
     */
    public function foundLinkToBeScanned(Event $event, $link)
    {
        $this->io->verbose(__d('link-scanner', 'Link found: {0}', $link));

        return true;
    }

    /**
     * `LinkScanner.foundRedirect` event
     * @param Event $event An `Event` instance
     * @param string $url Redirect
     * @return bool
     * @uses $io
     */
    public function foundRedirect(Event $event, $url)
    {
        $this->io->verbose(__d('link-scanner', 'Redirect found: {0}', $url));

        return true;
    }

    /**
     * `LinkScanner.resultsExported` event
     * @param Event $event An `Event` instance
     * @param string $filename Filename
     * @return bool
     * @uses $io
     */
    public function resultsExported(Event $event, $filename)
    {
        $this->io->success(__d('link-scanner', 'Results have been exported to {0}', $filename));

        return true;
    }

    /**
     * `LinkScanner.resultsImported` event
     * @param Event $event An `Event` instance
     * @param string $filename Filename
     * @return bool
     */
    public function resultsImported(Event $event, $filename)
    {
        return true;
    }

    /**
     * `LinkScanner.scanCompleted` event
     * @param Event $event An `Event` instance
     * @param int $startTime Start time
     * @param int $endTime End time
     * @param ResultScan $ResultScan A `ResultScan` instance
     * @return bool
     * @uses $args
     * @uses $io
     */
    public function scanCompleted(Event $event, $startTime, $endTime, ResultScan $ResultScan)
    {
        if ($this->args->getOption('verbose')) {
            $this->io->hr();
        }

        $endTime = new Time($endTime);
        $elapsedTime = $endTime->diffForHumans(new Time($startTime), true);

        $this->io->out(__d('link-scanner', 'Scan completed at {0}', $endTime->i18nFormat('yyyy-MM-dd HH:mm:ss')));
        $this->io->out(__d('link-scanner', 'Elapsed time: {0}', $elapsedTime));
        $this->io->out(__d('link-scanner', 'Total scanned links: {0}', $ResultScan->count()));

        if ($this->args->getOption('verbose')) {
            $this->io->hr();
        }

        return true;
    }

    /**
     * `LinkScanner.scanStarted` event
     * @param Event $event An `Event` instance
     * @param int $startTime Start time
     * @param string $fullBaseUrl Full base url
     * @return bool
     * @uses $args
     * @uses $io
     */
    public function scanStarted(Event $event, $startTime, $fullBaseUrl)
    {
        if ($this->args->getOption('verbose')) {
            $this->io->hr();
        }

        $startTime = (new Time($startTime))->i18nFormat('yyyy-MM-dd HH:mm:ss');
        $this->io->info(__d('link-scanner', 'Scan started for {0} at {1}', $fullBaseUrl, $startTime));

        if (!$this->args->getOption('verbose')) {
            return true;
        }

        $this->io->hr();

        $cache = Cache::getConfig(LINK_SCANNER);
        if (Cache::enabled() && !empty($cache['duration'])) {
            $this->io->success(__d('link-scanner', 'The cache is enabled and its duration is `{0}`', $cache['duration']));
        } else {
            $this->io->info(__d('link-scanner', 'The cache is disabled'));
        }

        if ($this->args->getOption('force')) {
            $this->io->info(__d('link-scanner', 'Force mode is enabled'));
        } else {
            $this->io->info(__d('link-scanner', 'Force mode is not enabled'));
        }

        if ($event->getSubject()->getConfig('externalLinks')) {
            $this->io->info(__d('link-scanner', 'Scanning of external links is enabled'));
        } else {
            $this->io->info(__d('link-scanner', 'Scanning of external links is not enabled'));
        }

        if ($event->getSubject()->getConfig('followRedirects')) {
            $this->io->info(__d('link-scanner', 'Redirects will be followed'));
        } else {
            $this->io->info(__d('link-scanner', 'Redirects will not be followed'));
        }

        $maxDepth = $event->getSubject()->getConfig('maxDepth');
        if (is_positive($maxDepth)) {
            $this->io->info(__d('link-scanner', 'Maximum depth of the scan: {0}', $maxDepth));
        }

        $timeout = $event->getSubject()->Client->getConfig('timeout');
        if (is_positive($timeout)) {
            $this->io->info(__d('link-scanner', 'Timeout in seconds for GET requests: {0}', $timeout));
        }

        $this->io->hr();

        return true;
    }
}
