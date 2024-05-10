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

if (!function_exists('clean_url')) {
    /**
     * Cleans an url. It removes all unnecessary parts, as fragment (#), trailing slash and `www` prefix
     * @param string $url Url
     * @param bool $removeWWW Removes the www prefix
     * @param bool $removeTrailingSlash Removes the trailing slash
     * @return string
     * @since 1.2.1
     */
    function clean_url(string $url, bool $removeWWW = false, bool $removeTrailingSlash = false): string
    {
        $url = preg_replace('/(#.*)$/', '', $url) ?: '';
        if ($removeWWW) {
            $url = preg_replace('/^((http|https|ftp):\/\/)?www\./', '$1', $url) ?: '';
        }

        return $removeTrailingSlash ? rtrim($url, '/') : $url;
    }
}

if (!function_exists('get_hostname_from_url')) {
    /**
     * Gets the host name from an url.
     *
     * It also removes the `www` prefix.
     * @param string $url Url
     * @return string
     * @since 1.2.1
     */
    function get_hostname_from_url(string $url): string
    {
        $hostname = parse_url($url, PHP_URL_HOST) ?: '';

        return str_starts_with($hostname, 'www.') ? substr($hostname, 4) : $hostname;
    }
}

if (!function_exists('is_external_url')) {
    /**
     * Checks if an url is external, relative to the passed hostname
     * @param string $url Url to check
     * @param string $hostname Hostname for the comparison
     * @return bool
     * @since 1.2.1
     */
    function is_external_url(string $url, string $hostname): bool
    {
        $hostForUrl = get_hostname_from_url($url);

        //Url with the same host and relative url are not external
        return $hostForUrl && strcasecmp($hostForUrl, $hostname) !== 0;
    }
}
