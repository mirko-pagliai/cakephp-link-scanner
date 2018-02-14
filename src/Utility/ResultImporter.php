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
namespace LinkScanner\Utility;

use Cake\Network\Exception\InternalErrorException;
use Cake\Utility\Inflector;
use Cake\Utility\Xml;
use DOMDocument;
use Exception;
use LinkScanner\ResultScan;

/**
 * A class to import the scan results.
 *
 * This class can be initialized. But it is advisable to use the
 *  `LinkScanner::import()` method.
 */
class ResultImporter
{
    /**
     * Internal method to read data
     * @param string $filename Filename to import
     * @return string
     * @throws InternalErrorException
     */
    protected function read($filename)
    {
        if (!is_readable($filename)) {
            throw new InternalErrorException(__('File or directory `{0}` not readable', $filename));
        }

        return trim(file_get_contents($filename));
    }

    /**
     * Imports results as array
     * @param string $filename Filename to import
     * @return string
     * @throws InternalErrorException
     * @uses read()
     */
    public function asArray($filename)
    {
        //@codingStandardsIgnoreLine
        $data = @unserialize($this->read($filename));

        if ($data === false || empty($data) || !is_array($data)) {
            throw new InternalErrorException(__('Invalid data'));
        }

        return $data;
    }

    /**
     * Imports results as html
     * @param string $filename Filename to import
     * @return string
     * @throws InternalErrorException
     * @uses read()
     */
    public function asHtml($filename)
    {
        try {
            $dom = new DOMDocument;
            $dom->loadHTML($this->read($filename));

            foreach ($dom->getElementsByTagName('p') as $element) {
                list($name, $value) = explode(': ', $element->nodeValue);

                $content[Inflector::variable($name)] = $value;
            }
        } catch (Exception $e) {
            throw new InternalErrorException(__('Invalid data'));
        }

        if (array_keys($content) !== ['fullBaseUrl', 'maxDepth', 'startTime', 'endTime', 'checkedLinks']) {
            throw new InternalErrorException(__('Invalid data'));
        }

        $tbodyElement = $dom->getElementsByTagName('tbody');

        if (!$tbodyElement->length) {
            throw new InternalErrorException(__('Invalid data'));
        }

        $ResultScan = [];

        foreach ($tbodyElement->item(0)->getElementsByTagName('tr') as $element) {
            list($url, $code, $external, $type) = array_map(function ($element) {
                return $element->nodeValue;
            }, iterator_to_array($element->getElementsByTagName('td')));

            $external = $external === 'Yes';

            $ResultScan[] = compact('url', 'code', 'external', 'type');
        }

        $content['ResultScan'] = new ResultScan($ResultScan);

        if ($content['ResultScan']->isEmpty()) {
            throw new InternalErrorException(__('Invalid data'));
        }

        return $content;
    }

    /**
     * Imports results as xml
     * @param string $filename Filename to import
     * @return string
     * @throws InternalErrorException
     * @uses read()
     */
    public function asXml($filename)
    {
        $parseLinks = function ($link) {
            if (array_keys($link) !== ['code', 'external', 'type', 'url']) {
                throw new InternalErrorException(__('Invalid data'));
            }

            $link['external'] = (bool)$link['external'];

            return $link;
        };

        $data = $this->read($filename);

        try {
            $content = Xml::toArray(Xml::build($data))['root'];
            $content['ResultScan'] = (new ResultScan($content['ResultScan']['link']))->map($parseLinks);

            return $content;
        } catch (Exception $e) {
            throw new InternalErrorException(__('Invalid data'));
        }
    }
}
