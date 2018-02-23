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
namespace LinkScanner\TestSuite;

use LinkScanner\Utility\LinkScanner;
use Zend\Diactoros\Stream;

/**
 * This trait provided the `getLinkScannerClientReturnsSampleResponse()` method
 */
trait LinkScannerWithStubClientTrait
{
    /**
     * Internal method to get a `LinkScanner` instance with a stub for the
     *  `Client` instance.
     * @return LinkScanner
     */
    protected function getLinkScannerClientReturnsSampleResponse()
    {
        $this->LinkScanner = new LinkScanner('http://google.com');

        $this->LinkScanner->Client = $this->getMockBuilder(get_class($this->LinkScanner->Client))
            ->setMethods(['get'])
            ->getMock();

        $this->LinkScanner->Client->method('get')
            ->will($this->returnCallback(function () {
                $request = unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_response'));
                $body = unserialize(file_get_contents(TESTS . 'response_examples' . DS . 'google_body'));
                $stream = new Stream('php://memory', 'rw');
                $stream->write($body);
                $this->setProperty($request, 'stream', $stream);

                return $request;
            }));

        return $this->LinkScanner;
    }
}
