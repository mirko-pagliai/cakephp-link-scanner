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
namespace LinkScanner\Test\TestCase\Http\Client;

use Cake\Http\Client\Response;
use Cake\TestSuite\Stub\Response as StubResponse;
use Cake\TestSuite\TestCase;
use LinkScanner\Http\Client\ScanResponse;
use Reflection\ReflectionTrait;
use Zend\Diactoros\Stream;

/**
 * ScanResponseTest class
 */
class ScanResponseTest extends TestCase
{
    use ReflectionTrait;

    /**
     * Test for `bodyIsHtml()` method
     * @test
     */
    public function testBodyIsHtml()
    {
        foreach ([
            '<b>String</b>' => true,
            '</b>' => true,
            '<b>String' => true,
            '<tag>String</tag>' => true,
            'String' => false,
            '' => false,
        ] as $string => $expected) {
            $stream = new Stream('php://memory', 'rw');
            $stream->write($string);
            $response = new Response;
            $this->setProperty($response, 'stream', $stream);
            $ScanResponse = new ScanResponse($response);

            $this->assertEquals($expected, $ScanResponse->bodyIsHtml());
        }
    }

    /**
     * Test for `isOk()` method
     * @test
     */
    public function testIsOk()
    {
        foreach ([new Response, new StubResponse] as $response) {
            $response = $response->withStatus(200);
            $ScanResponse = new ScanResponse($response);
            $this->assertTrue($ScanResponse->isOk());
        }
    }
}
