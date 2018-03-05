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
//Sets the default LinkScanner name
if (!defined('LINK_SCANNER')) {
    define('LINK_SCANNER', 'LinkScanner');
}

//Sets the path of the lock file. Use `false` if you don't want to use a lock file
if (!defined('LINK_SCANNER_LOCK_FILE')) {
    define('LINK_SCANNER_LOCK_FILE', TMP . 'link_scanner_lock_file');
}
