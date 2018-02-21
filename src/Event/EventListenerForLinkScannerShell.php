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
class EventListenerForLinkScannerShell implements EventListenerInterface
{
    /**
     * @var \LinkScanner\Shell\LinkScannerShell
     */
    protected $Shell;

    /**
     * Construct
     * @param LinkScannerShell $shell A `LinkScannerShell` instance
     * @uses $Shell
     */
    public function __construct(LinkScannerShell $shell)
    {
        $this->Shell = $shell;
    }

    /**
     * Returns a list of events this object is implementing. When the class is
     *  registered in an event manager, each individual method will be
     *  associated with the respective event
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'LinkScanner.afterScanUrl' => 'afterScanUrl',
            'LinkScanner.beforeScanUrl' => 'beforeScanUrl',
            'LinkScanner.foundLinksToBeScanned' => 'foundLinksToBeScanned',
            'LinkScanner.scanCompleted' => 'scanCompleted',
            'LinkScanner.scanStarted' => 'startScan',
        ];
    }

    /**
     * `LinkScanner.afterScanUrl` event
     * @param Event $event An `Event` instance
     * @param ScanResponse $response A `ScanResponse` instance
     * @return bool
     * @uses $Shell
     */
    public function afterScanUrl(Event $event, ScanResponse $response)
    {
        if (!$this->Shell->param('verbose')) {
            return true;
        }

        $out = $response->getStatusCode();

        if ($response->isOk()) {
            $out = '<success>' . __d('link-scanner', 'OK') . '</success>';
        }

        $this->Shell->out($out);

        return true;
    }

    /**
     * `LinkScanner.beforeScanUrl` event
     * @param Event $event An `Event` instance
     * @param string $url Url
     * @return bool
     * @uses $Shell
     */
    public function beforeScanUrl(Event $event, $url)
    {
        if (!$this->Shell->param('verbose')) {
            return true;
        }

        $this->Shell->out(__d('link-scanner', 'Checking {0} ...', $url), 0);

        return true;
    }

    /**
     * `LinkScanner.foundLinksToBeScanned` event
     * @param Event $event An `Event` instance
     * @param array $linksToScan Links to scan
     * @return bool
     * @uses $Shell
     */
    public function foundLinksToBeScanned(Event $event, $linksToScan)
    {
        $count = count($linksToScan);

        if (!$this->Shell->param('verbose') || !$count) {
            return true;
        }

        $this->Shell->out(__d('link-scanner', 'Links found: {0}', $count));

        return true;
    }

    /**
     * `LinkScanner.scanCompleted` event
     * @param Event $event An `Event` instance
     * @param int $endTime End time
     * @param ResultScan $ResultScan A `ResultScan` instance
     * @return bool
     * @uses $Shell
     */
    public function scanCompleted(Event $event, $endTime, ResultScan $ResultScan)
    {
        if ($this->Shell->param('verbose')) {
            $this->Shell->hr();
        }

        $this->Shell->out(__d('link-scanner', 'Scan completed at {0}', (new Time($endTime))->i18nFormat('yyyy-MM-dd HH:mm:ss')));
        $this->Shell->out(__d('link-scanner', 'Total scanned links: {0}', $ResultScan->count()));

        if ($this->Shell->param('verbose')) {
            $this->Shell->hr();
        }

        return true;
    }

    /**
     * `LinkScanner.startScan` event
     * @param Event $event An `Event` instance
     * @param int $startTime Start time
     * @param string $fullBaseUrl Full base url
     * @return bool
     * @uses $Shell
     */
    public function startScan(Event $event, $startTime, $fullBaseUrl)
    {
        if ($this->Shell->param('verbose')) {
            $this->Shell->hr();
        }

        $this->Shell->out(__d(
            'link-scanner',
            'Scan started for {0} at {1}',
            $fullBaseUrl,
            (new Time($startTime))->i18nFormat('yyyy-MM-dd HH:mm:ss')
        ));

        if ($this->Shell->param('verbose')) {
            $this->Shell->hr();
        }

        return true;
    }
}
