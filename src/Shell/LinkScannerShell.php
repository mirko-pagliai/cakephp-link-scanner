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
use Exception;
use LinkScanner\Event\LinkScannerShellEventListener;
use LinkScanner\Utility\LinkScanner;
use MeTools\Console\Shell;

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
     * @return void
     * @see LinkScannerShellEventListener::implementedEvents()
     * @uses LinkScanner
     */
    public function scan()
    {
        try {
            //This method will trigger events provided by `LinkScannerShellEventListener`
            $this->LinkScanner->getEventManager()->on(new LinkScannerShellEventListener($this));

            if ($this->hasParam('disable-external-links')) {
                $this->LinkScanner->setConfig('externalLinks', false);
            }
            if ($this->hasParam('force')) {
                safe_unlink(LINK_SCANNER_LOCK_FILE);
            }
            if ($this->param('full-base-url')) {
                $this->LinkScanner->setFullBaseUrl($this->param('full-base-url'));
            }
            if ($this->param('max-depth')) {
                $this->LinkScanner->setConfig('maxDepth', $this->param('max-depth'));
            }
            if ($this->param('timeout')) {
                $this->LinkScanner->Client->setConfig('timeout', $this->param('timeout'));
            }

            $this->LinkScanner->scan();

            if ($this->hasParam('export')) {
                $this->LinkScanner->export($this->param('export'));
            }
        } catch (Exception $e) {
            $this->abort($e->getMessage());
        }
    }

    /**
     * Gets the option parser instance and configures it
     * @return ConsoleOptionParser
     * @uses $LinkScanner
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        $parser->setDescription(__d('database_backup', 'Shell to perform links scanner'));

        $parser->addSubcommand('scan', [
            'help' => __d('link-scanner', 'Performs a complete scan'),
            'parser' => [
                'options' => [
                    'disable-external-links' => [
                        'help' => __d('link-scanner', 'Disable the scanning of external links'),
                    ],
                    'export' => [
                        'help' => __d('link-scanner', 'Export results. The filename will be generated automatically, or you can indicate a relative or absolute path'),
                        'short' => 'e',
                    ],
                    'force' => [
                        'help' => __d('link-scanner', 'Force mode: removes the lock file and does not ask questions'),
                        'short' => 'f',
                    ],
                    'full-base-url' => [
                        'help' => __d('link-scanner', 'Full base url. By default, the `{0}` value will be used', 'App.fullBaseUrl'),
                    ],
                    'max-depth' => [
                        'help' => __d('link-scanner', 'Maximum depth of the scan. Default: {0}', $this->LinkScanner->getConfig('maxDepth')),
                        'short' => 'd',
                    ],
                    'timeout' => [
                        'help' => __d('link-scanner', 'Timeout in seconds for each request. Default: {0}', $this->LinkScanner->Client->getConfig('timeout')),
                        'short' => 't',
                    ],
                ],
            ],
        ]);

        return $parser;
    }
}
