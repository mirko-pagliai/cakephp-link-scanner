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

    /**
     * Test for `statusCodeIsOk()` global function
     * @test
     */
    public function testStatusCodeIsOk()
    {
        $this->assertTrue(statusCodeIsOk(200));
        $this->assertFalse(statusCodeIsOk(500));
    }
}
