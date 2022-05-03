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
namespace LinkScanner\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use LinkScanner\Event\LinkScannerCommandEventListener;
use LinkScanner\Utility\LinkScanner;
use MeTools\Console\Command;
use PHPUnit\Framework\Exception as PHPUnitException;

/**
 * A link scanner command
 */
class LinkScannerCommand extends Command
{
    /**
     * A `LinkScanner` instance
     * @var \LinkScanner\Utility\LinkScanner
     */
    public LinkScanner $LinkScanner;

    /**
     * Hook method invoked by CakePHP when a command is about to be executed
     * @return void
     */
    public function initialize(): void
    {
        $this->LinkScanner = $this->LinkScanner ?: new LinkScanner();
    }

    /**
     * Performs a complete scan
     * @param \Cake\Console\Arguments $args The command arguments
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     * @see LinkScannerCommandEventListener::implementedEvents()
     */
    public function execute(Arguments $args, ConsoleIo $io): void
    {
        try {
            //Will trigger events provided by `LinkScannerCommandEventListener`
            $this->LinkScanner->getEventManager()->on(new LinkScannerCommandEventListener($args, $io));

            if ($args->getOption('export-only-bad-results')) {
                $this->LinkScanner->setConfig('exportOnlyBadResults', true);
            }
            if ($args->getOption('follow-redirects')) {
                $this->LinkScanner->setConfig('followRedirects', true);
            }
            if ($args->getOption('force')) {
                $this->LinkScanner->setConfig('lockFile', false);
            }
            if ($args->getOption('full-base-url')) {
                $this->LinkScanner->setConfig('fullBaseUrl', $args->getOption('full-base-url'));
            }
            if ($args->getOption('max-depth')) {
                $this->LinkScanner->setConfig('maxDepth', $args->getOption('max-depth'));
            }
            if ($args->getOption('no-cache')) {
                $this->LinkScanner->setConfig('cache', false);
            }
            if ($args->getOption('no-external-links')) {
                $this->LinkScanner->setConfig('externalLinks', false);
            }
            if ($args->getOption('timeout')) {
                $this->LinkScanner->Client->setConfig('timeout', $args->getOption('timeout'));
            }

            $this->LinkScanner->scan();

            if ($args->getOption('export') || $args->getOption('export-only-bad-results') || $args->getOption('export-with-filename')) {
                $this->LinkScanner->export((string)$args->getOption('export-with-filename'));
            }
        } catch (PHPUnitException $e) {
            throw $e;
        } catch (Exception $e) {
            $io->error($e->getMessage());
            $this->abort();
        }
    }

    /**
     * Hook method for defining this command's option parser
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(__d('link-scanner', 'Performs a complete scan'));

        return $parser->addOptions([
            'export' => [
                'boolean' => true,
                'default' => false,
                'help' => __d('link-scanner', 'Export results. The filename will be generated automatically'),
                'short' => 'e',
            ],
            'export-only-bad-results' => [
                'boolean' => true,
                'default' => false,
                'help' => __d('link-scanner', 'Only negative results will be exported (status code 400 or 500).
                    This allows you to save space for exported files'),
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
                'help' => __d(
                    'link-scanner',
                    'Full base url. By default, the `{0}` value will be used',
                    'App.fullBaseUrl'
                ),
            ],
            'max-depth' => [
                'help' => __d(
                    'link-scanner',
                    'Maximum depth of the scan. Default: {0}',
                    $this->LinkScanner->getConfig('maxDepth')
                ),
                'short' => 'd',
            ],
            'no-cache' => [
                'boolean' => true,
                'default' => false,
                'help' => __d('link-scanner', 'Disables the cache'),
            ],
            'no-external-links' => [
                'boolean' => true,
                'default' => false,
                'help' => __d('link-scanner', 'Disable the scanning of external links'),
            ],
            'timeout' => [
                'help' => __d(
                    'link-scanner',
                    'Timeout in seconds for GET requests. Default: {0}',
                    $this->LinkScanner->Client->getConfig('timeout')
                ),
                'short' => 't',
            ],
        ]);
    }
}
