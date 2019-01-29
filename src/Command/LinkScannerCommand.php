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
namespace LinkScanner\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use LinkScanner\Event\LinkScannerCommandEventListener;
use LinkScanner\Utility\LinkScanner;
use MeTools\Console\Command;

/**
 * A link scanner command
 */
class LinkScannerCommand extends Command
{
    /**
     * @var LinkScanner\Utility\LinkScanner
     */
    public $LinkScanner;

    /**
     * Performs a complete scan
     * @param Arguments $args The command arguments
     * @param ConsoleIo $io The console io
     * @return null|int The exit code or null for success
     * @see LinkScannerCommandEventListener::implementedEvents()
     * @uses LinkScanner
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->LinkScanner = $this->LinkScanner ?: new LinkScanner;

        try {
            //Will trigger events provided by `LinkScannerCommandEventListener`
            $this->LinkScanner->getEventManager()->on(new LinkScannerCommandEventListener($args, $io));

            if ($args->getOption('disable-external-links')) {
                $this->LinkScanner->setConfig('externalLinks', false);
            }
            if ($args->getOption('follow-redirects')) {
                $this->LinkScanner->setConfig('followRedirects', true);
            }
            if ($args->getOption('force')) {
                @unlink(LINK_SCANNER_LOCK_FILE);
            }
            if ($args->hasOption('full-base-url')) {
                $this->LinkScanner->setFullBaseUrl($args->getOption('full-base-url'));
            }
            if ($args->hasOption('max-depth')) {
                $this->LinkScanner->setConfig('maxDepth', $args->getOption('max-depth'));
            }
            if ($args->hasOption('timeout')) {
                $this->LinkScanner->Client->setConfig('timeout', $args->getOption('timeout'));
            }

            $this->LinkScanner->scan();

            if ($args->getOption('export-with-filename')) {
                $this->LinkScanner->export($args->getOption('export-with-filename'));
            } elseif ($args->getOption('export')) {
                $this->LinkScanner->export(null);
            }
        } catch (Exception $e) {
            $io->error($e->getMessage());
            $this->abort();
        }

        return null;
    }

    /**
     * Hook method for defining this command's option parser
     * @param ConsoleOptionParser $parser The parser to be defined
     * @return ConsoleOptionParser The built parser
     * @uses $LinkScanner
     */
    protected function buildOptionParser(ConsoleOptionParser $parser)
    {
        $parser->setDescription(__d('link-scanner', 'Performs a complete scan'));

        return $parser->addOptions([
            'disable-external-links' => [
                'boolean' => true,
                'default' => false,
                'help' => __d('link-scanner', 'Disable the scanning of external links'),
            ],
            'export' => [
                'boolean' => true,
                'default' => false,
                'help' => __d('link-scanner', 'Export results. The filename will be generated automatically'),
                'short' => 'e',
            ],
            'export-with-filename' => [
                'help' => __d('link-scanner', 'Export results. You must pass a relative or absolute path'),
            ],
            'follow-redirects' => [
                'boolean' => true,
                'default' => false,
                'help' => __d('link-scanner', 'Follows redirect'),
            ],
            'force' => [
                'boolean' => true,
                'default' => false,
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
                'help' => __d('link-scanner', 'Timeout in seconds for GET requests. Default: {0}', $this->LinkScanner->Client->getConfig('timeout')),
                'short' => 't',
            ],
        ]);
    }
}
