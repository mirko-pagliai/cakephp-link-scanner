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

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\I18n\Time;
use LinkScanner\Http\Client\ScanResponse;
use LinkScanner\ResultScan;
use LinkScanner\Shell\LinkScannerShell;

/**
 * Event listener for the `LinkScannerShell` class.
 *
 * This class provided methods to be performed as events by `LinkScannerShell`.
 */
class LinkScannerShellEventListener implements EventListenerInterface
{
    /**
     * @var \LinkScanner\Shell\LinkScannerShell
     */
    protected $LinkScannerShell;

    /**
     * Construct
     * @param LinkScannerShell $shell A `LinkScannerShell` instance
     * @uses $LinkScannerShell
     */
    public function __construct(LinkScannerShell $shell)
    {
        $this->LinkScannerShell = $shell;
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
     * @uses $LinkScannerShell
     */
    public function afterScanUrl(Event $event, ScanResponse $response)
    {
        if (!$this->LinkScannerShell->param('verbose')) {
            return true;
        }

        if ($response->isOk()) {
            $this->LinkScannerShell->success(__d('link-scanner', 'OK'));
        } else {
            $this->LinkScannerShell->out($response->getStatusCode());
        }

        return true;
    }

    /**
     * `LinkScanner.beforeScanUrl` event
     * @param Event $event An `Event` instance
     * @param string $url Url
     * @return bool
     * @uses $LinkScannerShell
     */
    public function beforeScanUrl(Event $event, $url)
    {
        if ($this->LinkScannerShell->param('verbose')) {
            $this->LinkScannerShell->out(__d('link-scanner', 'Checking {0} ...', $url), 0);
        }

        return true;
    }

    /**
     * `LinkScanner.foundLinkToBeScanned` event
     * @param Event $event An `Event` instance
     * @param string $link Link
     * @return bool
     */
    public function foundLinkToBeScanned(Event $event, $link)
    {
        if ($this->LinkScannerShell->param('verbose')) {
            $this->LinkScannerShell->out(__d('link-scanner', 'Link found: {0}', $link));
        }

        return true;
    }

    /**
     * `LinkScanner.resultsExported` event
     * @param Event $event An `Event` instance
     * @param string $filename End time Filename
     * @return bool
     * @uses $LinkScannerShell
     */
    public function resultsExported(Event $event, $filename)
    {
        $this->LinkScannerShell->success(__d('link-scanner', 'Results have been exported to {0}', $filename));

        return true;
    }

    /**
     * `LinkScanner.scanCompleted` event
     * @param Event $event An `Event` instance
     * @param int $endTime End time
     * @param ResultScan $ResultScan A `ResultScan` instance
     * @return bool
     * @uses $LinkScannerShell
     */
    public function scanCompleted(Event $event, $endTime, ResultScan $ResultScan)
    {
        if ($this->LinkScannerShell->param('verbose')) {
            $this->LinkScannerShell->hr();
        }

        $endTime = (new Time($endTime))->i18nFormat('yyyy-MM-dd HH:mm:ss');
        $this->LinkScannerShell->out(__d('link-scanner', 'Scan completed at {0}', $endTime));
        $this->LinkScannerShell->out(__d('link-scanner', 'Total scanned links: {0}', $ResultScan->count()));

        if ($this->LinkScannerShell->param('verbose')) {
            $this->LinkScannerShell->hr();
        }

        return true;
    }

    /**
     * `LinkScanner.scanStarted` event
     * @param Event $event An `Event` instance
     * @param int $startTime Start time
     * @param string $fullBaseUrl Full base url
     * @return bool
     * @uses $LinkScannerShell
     */
    public function scanStarted(Event $event, $startTime, $fullBaseUrl)
    {
        if ($this->LinkScannerShell->param('verbose')) {
            $this->LinkScannerShell->hr();
        }

        $startTime = (new Time($startTime))->i18nFormat('yyyy-MM-dd HH:mm:ss');
        $this->LinkScannerShell->out(__d('link-scanner', 'Scan started for {0} at {1}', $fullBaseUrl, $startTime));

        if ($this->LinkScannerShell->param('verbose')) {
            $this->LinkScannerShell->hr();
        }

        return true;
    }
}
