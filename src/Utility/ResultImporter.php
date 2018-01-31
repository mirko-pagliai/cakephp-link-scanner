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

use Cake\Utility\Inflector;
use Cake\Utility\Xml;
use DOMDocument;

class ResultImporter
{
    /**
     * Internal method to read data
     * @param string $filename Filename to import
     * @return string
     */
    protected function read($filename)
    {
        return trim(file_get_contents($filename));
    }

    /**
     * Imports results as array
     * @param string $filename Filename to import
     * @return string
     */
    public function asArray($filename)
    {
        return unserialize($this->read($filename));
    }

    /**
     * Imports results as html
     * @param string $filename Filename to import
     * @return string
     */
    public function asHtml($filename)
    {
        $dom = new DOMDocument;
        $dom->loadHTML($this->read($filename));

        foreach ($dom->getElementsByTagName('p') as $element) {
            list($name, $value) = (explode(': ', $element->nodeValue));

            $content[Inflector::variable($name)] = $value;
        }

        $table = $dom->getElementsByTagName('table')->item(0)->getElementsByTagName('tbody')->item(0);

        foreach ($table->getElementsByTagName('tr') as $element) {
            list($url, $code, $external, $type) = array_map(function ($element) {
                return $element->nodeValue;
            }, iterator_to_array($element->getElementsByTagName('td')));

            $external = $external === 'Yes';

            $content['links'][] = compact('url', 'code', 'external', 'type');
        }

        return $content;
    }

    /**
     * Imports results as xml
     * @param string $filename Filename to import
     * @return string
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
