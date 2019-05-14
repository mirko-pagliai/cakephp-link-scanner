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
use Cake\Http\Client\Response;
use LinkScanner\ResultScan;

/**
 * Event listener interface for the `LinkScanner`
 */
interface LinkScannerEventListenerInterface extends EventListenerInterface
{
    /**
     * `LinkScanner.afterScanUrl` event
     * @param \Cake\Event\Event $event An `Event` instance
     * @param \Cake\Http\Client\Response $response A `Response` instance
     * @return bool
     */
    public function afterScanUrl(Event $event, Response $response);

    /**
     * `LinkScanner.beforeScanUrl` event
     * @param \Cake\Event\Event $event An `Event` instance
     * @param string $url Url
     * @return bool
     */
    public function beforeScanUrl(Event $event, $url);

    /**
     * `LinkScanner.foundLinkToBeScanned` event
     * @param \Cake\Event\Event $event An `Event` instance
     * @param string $link Link
     * @return bool
     */
    public function foundLinkToBeScanned(Event $event, $link);

    /**
     * `LinkScanner.foundRedirect` event
     * @param \Cake\Event\Event $event An `Event` instance
     * @param string $redirect Redirect
     * @return bool
     */
    public function foundRedirect(Event $event, $redirect);

    /**
     * `LinkScanner.resultsExported` event
     * @param \Cake\Event\Event $event An `Event` instance
     * @param string $filename Filename
     * @return bool
     */
    public function resultsExported(Event $event, $filename);

    /**
     * `LinkScanner.resultsImported` event
     * @param \Cake\Event\Event $event An `Event` instance
     * @param string $filename Filename
     * @return bool
     */
    public function resultsImported(Event $event, $filename);

    /**
     * `LinkScanner.scanCompleted` event
     * @param \Cake\Event\Event $event An `Event` instance
     * @param int $startTime Start time
     * @param int $endTime End time
     * @param \LinkScanner\ResultScan $ResultScan A `ResultScan` instance
     * @return bool
     */
    public function scanCompleted(Event $event, $startTime, $endTime, ResultScan $ResultScan);

    /**
     * `LinkScanner.scanStarted` event
     * @param \Cake\Event\Event $event An `Event` instance
     * @param int $startTime Start time
     * @param string $fullBaseUrl Full base url
     * @return bool
     */
    public function scanStarted(Event $event, $startTime, $fullBaseUrl);
}
