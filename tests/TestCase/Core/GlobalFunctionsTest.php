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
namespace LinkScanner\Test\TestCase\Core;

use Cake\TestSuite\TestCase;

/**
 * GlobalFunctionsTest class
 */
class GlobalFunctionsTest extends TestCase
{
    /**
     * Test for `clearUrl()` global function
     * @test
     */
    public function testClearUrl()
    {
        foreach ([
            'http://mysite',
            'http://mysite/',
            'http://mysite#fragment',
            'http://mysite/#fragment',
        ] as $url) {
            $this->assertEquals('http://mysite', clearUrl($url));
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
            $this->assertEquals('relative', clearUrl($url));
        }
    }

    /**
     * Test for `getHostnameFromUrl()` global function
     * @test
     */
    public function testGetHostnameFromUrl()
    {
        foreach (['http://127.0.0.1', 'http://127.0.0.1/'] as $url) {
            $this->assertEquals('127.0.0.1', getHostnameFromUrl($url));
        }

        foreach (['http://localhost', 'http://localhost/'] as $url) {
            $this->assertEquals('localhost', getHostnameFromUrl($url));
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
            $this->assertEquals('google.com', getHostnameFromUrl($url));
        }

        $this->assertNull(getHostnameFromUrl('page.html'));
    }

    /**
     * Test for `isUrl()` global function
     * @test
     */
    public function testIsUrl()
    {
        foreach ([
            'https://www.example.com',
            'http://www.example.com',
            'www.example.com',
            'http://example.com',
            'http://example.com/file',
            'http://example.com/file.html',
            'http://example.com/subdir/file',
            'ftp://www.example.com',
            'ftp://example.com',
            'ftp://example.com/file.html',
        ] as $url) {
            $this->assertTrue(isUrl($url));
        }

        foreach ([
            'example.com',
            'folder',
            DS . 'folder',
            DS . 'folder' . DS,
            DS . 'folder' . DS . 'file.txt',
        ] as $badUrl) {
            $this->assertFalse(isUrl($badUrl));
        }
    }
}
