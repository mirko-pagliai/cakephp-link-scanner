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
        $data = $this->read($filename);

        if (empty($data) || !is_string($data)) {
            throw new InternalErrorException(__('Invalid data'));
        }

        $dom = new DOMDocument;
        $dom->loadHTML($data);

        foreach ($dom->getElementsByTagName('p') as $element) {
            //@codingStandardsIgnoreLine
            @list($name, $value) = explode(': ', $element->nodeValue);

            $content[Inflector::variable($name)] = $value;
        }

        if (array_keys($content) !== ['fullBaseUrl', 'maxDepth', 'startTime', 'elapsedTime', 'checkedLinks']) {
            throw new InternalErrorException(__('Invalid data'));
        }

        $tbodyElement = $dom->getElementsByTagName('tbody');

        if (!$tbodyElement->length) {
            throw new InternalErrorException(__('Invalid data'));
        }

        foreach ($tbodyElement->item(0)->getElementsByTagName('tr') as $element) {
            list($url, $code, $external, $type) = array_map(function ($element) {
                return $element->nodeValue;
            }, iterator_to_array($element->getElementsByTagName('td')));

            $external = $external === 'Yes';

            $content['links'][] = compact('url', 'code', 'external', 'type');
        }

        if (empty($content['links'])) {
            throw new InternalErrorException(__('Invalid data'));
        }

        return $content;
    }

    /**
     * Imports results as xml
     * @param string $filename Filename to import
     * @return string
     * @uses read()
     */
    public function asXml($filename)
    {
        $content = Xml::toArray(Xml::build($this->read($filename)))['root'];

        $content['links'] = (array_map(function ($link) {
            $link['external'] = (bool)$link['external'];

            return $link;
        }, $content['links']['link']));

        return $content;
    }
}
