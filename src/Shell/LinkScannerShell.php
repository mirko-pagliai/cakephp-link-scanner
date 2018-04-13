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
namespace LinkScanner\Shell;

use Cake\Console\ConsoleIo;
use Cake\Console\Shell;
use Exception;
use LinkScanner\Event\EventListenerForLinkScannerShell;
use LinkScanner\Utility\LinkScanner;

/**
 * A link scanner shell
 */
class LinkScannerShell extends Shell
{
    /**
     * A `LinkScanner` instance
     * @var LinkScanner\Utility\LinkScanner
     */
    public $LinkScanner;

    /**
     * Construct
     * @param ConsoleIo|null $io A `ConsoleIo` instance
     * @uses $LinkScanner
     */
    public function __construct(ConsoleIo $io = null)
    {
        parent::__construct($io);

        $this->LinkScanner = new LinkScanner;
    }

    /**
     * Performs a complete scan
     * @param string $filename Filename where to export
     * @return void
     * @see EventListenerForLinkScannerShell::implementedEvents()
     * @uses LinkScanner
     */
    public function scan($filename)
    {
        try {
            //This method will trigger events provided by `EventListenerForLinkScannerShell`
            $this->LinkScanner->getEventManager()->on(new EventListenerForLinkScannerShell($this));

            if ($this->param('fullBaseUrl')) {
                $this->LinkScanner->setFullBaseUrl($this->param('fullBaseUrl'));
            }
            if ($this->param('maxDepth')) {
                $this->LinkScanner->setMaxDepth($this->param('maxDepth'));
            }
            if ($this->param('timeout')) {
                $this->LinkScanner->setTimeout($this->param('timeout'));
            }

            $this->LinkScanner->scan();
            $this->LinkScanner->export($filename);
        } catch (Exception $e) {
            $this->abort($e->getMessage());
        }
    }

    /**
     * Gets the option parser instance and configures it
     * @return ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        $parser->setDescription(__d('database_backup', 'Shell to perform links scanner'));

        $parser->addSubcommand('scan', [
            'help' => __d('link-scanner', 'Performs a complete scan'),
            'parser' => [
                'arguments' => [
                    'filename' => [
                        'help' => __d('link-scanner', 'Path to the file where to export results'),
                        'required' => true,
                    ],
                ],
                'options' => [
                    'maxDepth' => [
                        'help' => __d('link-scanner', 'Maximum depth of the scan. Default: {0}', $this->LinkScanner->maxDepth),
                        'short' => 'd',
                    ],
                    'fullBaseUrl' => [
                        'help' => __d('link-scanner', 'Full base url. By default, the `{0}` value will be used', 'App.fullBaseUrl'),
                        'short' => 'f',
                    ],
                    'timeout' => [
                        'help' => __d('link-scanner', 'Timeout in seconds for each request. Default: {0}', $this->LinkScanner->timeout),
                        'short' => 't',
                    ],
                ],
            ],
        ]);

        return $parser;
    }
}
