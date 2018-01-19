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
use Cake\Http\Client\Message;

if (!function_exists('clearUrl')) {
    /**
     * Deletes all unnecessary parts of an url
     * @param string $url Url
     * @return string
     */
    function clearUrl($url)
    {
        //Removes fragment (#)
        $url = preg_replace('/(\#.*)$/', '', $url);
        //Removes trailing slash
        $url = preg_replace('{/$}', '', $url);

        return $url;
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

if (!function_exists('statusCodeIsOk')) {
    /**
     * Checks if a status code is ok
     * @param int $code Status code
     * @return boolean
     */
    function statusCodeIsOk($code) {
         $codes = [
             Message::STATUS_OK,
             Message::STATUS_CREATED,
             Message::STATUS_ACCEPTED,
             Message::STATUS_NON_AUTHORITATIVE_INFORMATION,
             Message::STATUS_NO_CONTENT,
        ];

         return in_array($code, $codes);
    }
}
