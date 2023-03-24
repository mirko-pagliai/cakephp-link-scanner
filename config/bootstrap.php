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

use Cake\Cache\Cache;

//Default LinkScanner tmp
if (!defined('LINK_SCANNER_TMP')) {
    define('LINK_SCANNER_TMP', TMP . 'cakephp-link-scanner' . DS);
}

if (!Cache::getConfig('LinkScanner')) {
    Cache::setConfig('LinkScanner', [
        'className' => 'File',
        'duration' => '+1 day',
        'path' => LINK_SCANNER_TMP . 'cache' . DS,
        'prefix' => 'link_scanner_',
    ]);
}
