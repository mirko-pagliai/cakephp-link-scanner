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

use Cake\Utility\Xml;

/**
 * A class to export the scan results.
 *
 * This class can be initialized. But it is advisable to use the
 *  `LinkScanner::export()` method.
 */
class ResultExporter
{
    /**
     * Results.
     *
     * Data ready to be exported.
     * @see __construct()
     * @var array
     */
    protected $results;

    /**
     * Construct
     * @param array $results Results. Data ready to be exported
     * @uses $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Internal method to write data
     * @param string $filename Filename where to export
     * @param mixed $data Data to write
     * @return bool
     */
    protected function write($filename, $data)
    {
        return (bool)file_put_contents($filename, $data);
    }

    /**
     * Exports results as array
     * @param string $filename Filename where to export
     * @return bool
     * @uses $results
     * @uses write()
     */
    public function asArray($filename)
    {
        $data = serialize($this->results);

        return $this->write($filename, $data);
    }

    /**
     * Exports results as html
     * @param string $filename Filename where to export
     * @return bool
     * @uses $results
     * @uses write()
     */
    public function asHtml($filename)
    {
        $data = '<p><strong>fullBaseUrl:</strong> {{fullBaseUrl}}</p>' . PHP_EOL .
            '<p><strong>maxDepth:</strong> {{maxDepth}}</p>' . PHP_EOL .
            '<p><strong>startTime:</strong> {{startTime}}</p>' . PHP_EOL .
            '<p><strong>elapsedTime:</strong> {{elapsedTime}}</p>' . PHP_EOL .
            '<p><strong>checkedLinks:</strong> {{checkedLinks}}</p>' . PHP_EOL .
            '<table>' . PHP_EOL .
            '<thead><tr><th>Url</th><th>Code</th><th>Type</th></tr><thead>' . PHP_EOL .
            '<tbody>' . PHP_EOL .
            '{{resultTable}}' .
            '</tbody>' . PHP_EOL .
            '</table>' . PHP_EOL;

        foreach (['fullBaseUrl', 'maxDepth', 'startTime', 'elapsedTime', 'checkedLinks'] as $var) {
            $data = str_replace('{{' . $var . '}}', $this->results[$var], $data);
        }

        $table = null;

        foreach ($this->results['links'] as $link) {
            $table .= '<tr>' . PHP_EOL .
                '<td>' . $link['url'] . '</td>' . PHP_EOL .
                '<td>' . $link['code'] . '</td>' . PHP_EOL .
                '<td>' . $link['type'] . '</td>' . PHP_EOL .
                '</tr>' . PHP_EOL;
        }

        $data = str_replace('{{resultTable}}', $table, $data);

        return $this->write($filename, $data);
    }

    /**
     * Exports results as html
     * @param string $filename Filename where to export
     * @param array $options Options to pass to the `Xml::fromArray()` method
     * @return bool
     * @see https://api.cakephp.org/3.5/class-Cake.Utility.Xml.html#_fromArray
     * @uses $results
     * @uses write()
     */
    public function asXml($filename, array $options = [])
    {
        $options += ['format' => 'tags', 'pretty' => true];
        $data = $this->results;
        $data['links'] = ['link' => $data['links']];

        $data = Xml::fromArray(['root' => $data], $options)
            ->asXML();

        return $this->write($filename, $data);
    }
}
