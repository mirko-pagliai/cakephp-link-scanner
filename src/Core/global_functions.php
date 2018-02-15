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
if (!function_exists('clearUrl')) {
    /**
     * Removes all unnecessary parts of an url.
     *
     * It removes fragment (#) and trailing slash.
     * @param string $url Url
     * @return string
     */
    function clearUrl($url)
    {
        return trim(preg_replace('/(\#.*)$/', '', $url), '/');
    }
}

if (!function_exists('isUrl')) {
    /**
     * Checks whether a url is invalid
     * @param string $url Url
     * @return bool
     */
    function isUrl($url)
    {
        return (bool)preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $url);
    }
}
