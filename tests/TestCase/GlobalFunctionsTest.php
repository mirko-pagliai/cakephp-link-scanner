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

namespace LinkScanner\Test\TestCase;

use LinkScanner\TestSuite\TestCase;

/**
 * GlobalFunctionsTest
 */
class GlobalFunctionsTest extends TestCase
{
    /**
     * @test
     * @uses \clean_url()
     */
    public function testCleanUrl(): void
    {
        foreach ([
            'http://mysite.com',
            'http://mysite.com/',
            'http://mysite.com#fragment',
            'http://mysite.com/#fragment',
        ] as $url) {
            $this->assertMatchesRegularExpression('/^http:\/\/mysite\.com\/?$/', clean_url($url));
        }

        foreach ([
            'relative',
            '/relative',
            'relative/',
            '/relative/',
            'relative#fragment',
            'relative/#fragment',
            '/relative#fragment',
            '/relative/#fragment',
        ] as $url) {
            $this->assertMatchesRegularExpression('/^\/?relative\/?$/', clean_url($url));
        }

        foreach ([
            'www.my-site.com',
            'http://www.my-site.com',
            'https://www.my-site.com',
            'ftp://www.my-site.com',
        ] as $url) {
            $this->assertMatchesRegularExpression('/^((https?|ftp):\/\/)?my-site\.com$/', clean_url($url, true));
        }

        foreach ([
            'http://my-site.com',
            'http://my-site.com/',
            'http://www.my-site.com',
            'http://www.my-site.com/',
        ] as $url) {
            $this->assertEquals('http://my-site.com', clean_url($url, true, true));
        }
    }

    /**
     * @test
     * @uses \get_hostname_from_url()
     */
    public function testGetHostnameFromUrl(): void
    {
        $this->assertEmpty(get_hostname_from_url('page.html'));

        foreach (['http://127.0.0.1', 'http://127.0.0.1/'] as $url) {
            $this->assertEquals('127.0.0.1', get_hostname_from_url($url));
        }

        foreach (['http://localhost', 'http://localhost/'] as $url) {
            $this->assertEquals('localhost', get_hostname_from_url($url));
        }

        foreach ([
             '//google.com',
             'http://google.com',
             'http://google.com/',
             'http://www.google.com',
             'https://google.com',
             'http://google.com/page',
             'http://google.com/page?name=value',
        ] as $url) {
            $this->assertEquals('google.com', get_hostname_from_url($url));
        }
    }

    /**
     * @test
     * @uses \is_external_url()
     */
    public function testIsExternalUrl(): void
    {
        foreach ([
            '//google.com',
            '//google.com/',
            'http://google.com',
            'http://google.com/',
            'http://www.google.com',
            'http://www.google.com/',
            'http://www.google.com/page.html',
            'https://google.com',
            'relative.html',
            '/relative.html',
        ] as $url) {
            $this->assertFalse(is_external_url($url, 'google.com'));
        }

        foreach ([
            '//site.com',
            'http://site.com',
            'http://www.site.com',
            'http://subdomain.google.com',
        ] as $url) {
            $this->assertTrue(is_external_url($url, 'google.com'));
        }
    }
}
