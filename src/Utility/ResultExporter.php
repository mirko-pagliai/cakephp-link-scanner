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
use Cake\Utility\Xml;
use LinkScanner\ResultScan;

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
     * @param string $fullBaseUrl Full base url
     * @param int $maxDepth Max depth
     * @param int $startTime Start time
     * @param int $endTime End time
     * @param ResultScan $ResultScan Instance of `ResultScan` that contains the
     *  results of the scan
     * @uses $results
     */
    public function __construct($fullBaseUrl, $maxDepth, $startTime, $endTime, ResultScan $ResultScan)
    {
        $this->results = compact('fullBaseUrl', 'maxDepth', 'startTime', 'endTime', 'ResultScan');
        $this->results['checkedLinks'] = $ResultScan->count();
    }

    /**
     * Internal method to write data
     * @param string $filename Filename where to export
     * @param mixed $data Data to write
     * @return string|bool Filename where to export or `false` on failure
     * @throws InternalErrorException
     */
    protected function write($filename, $data)
    {
        if (!is_writable(dirname($filename))) {
            throw new InternalErrorException(__('File or directory `{0}` not writable', dirname($filename)));
        }

        if (!file_put_contents($filename, $data)) {
            return false;
        }

        return $filename;
    }

    /**
     * Exports results as array
     * @param string $filename Filename where to export
     * @return string|bool Filename where to export or `false` on failure
     * @uses $results
     * @uses write()
     */
    public function asArray($filename)
    {
        return $this->write($filename, serialize($this->results));
    }

    /**
     * Exports results as html
     * @param string $filename Filename where to export
     * @return string|bool Filename where to export or `false` on failure
     * @throws InternalErrorException
     * @uses $results
     * @uses write()
     */
    public function asHtml($filename)
    {
        $data = '<p><strong>Full base url:</strong> {{fullBaseUrl}}</p>' . PHP_EOL .
            '<p><strong>Max depth:</strong> {{maxDepth}}</p>' . PHP_EOL .
            '<p><strong>Start time:</strong> {{startTime}}</p>' . PHP_EOL .
            '<p><strong>End time:</strong> {{endTime}}</p>' . PHP_EOL .
            '<p><strong>Checked links:</strong> {{checkedLinks}}</p>' . PHP_EOL .
            '<table>' . PHP_EOL .
            '<thead><tr><th>Url</th><th>Code</th><th>External</th><th>Type</th></tr><thead>' . PHP_EOL .
            '<tbody>' . PHP_EOL .
            '{{resultTable}}' .
            '</tbody>' . PHP_EOL .
            '</table>';

        foreach (['fullBaseUrl', 'maxDepth', 'startTime', 'endTime', 'checkedLinks'] as $var) {
            $data = str_replace('{{' . $var . '}}', $this->results[$var], $data);
        }

        $table = null;

        foreach ($this->results['ResultScan'] as $link) {
            if (array_keys($link) !== ['code', 'external', 'type', 'url']) {
                throw new InternalErrorException(__('Invalid data'));
            }

            $table .= '<tr>' . PHP_EOL .
                '<td>' . $link['url'] . '</td>' . PHP_EOL .
                '<td>' . $link['code'] . '</td>' . PHP_EOL .
                '<td>' . ($link['external'] ? 'Yes' : 'No') . '</td>' . PHP_EOL .
                '<td>' . $link['type'] . '</td>' . PHP_EOL .
                '</tr>' . PHP_EOL;
        }

        return $this->write($filename, str_replace('{{resultTable}}', $table, $data));
    }

    /**
     * Exports results as html
     * @param string $filename Filename where to export
     * @return string|bool Filename where to export or `false` on failure
     * @see https://api.cakephp.org/3.5/class-Cake.Utility.Xml.html#_fromArray
     * @uses $results
     * @uses write()
     */
    public function asXml($filename)
    {
        $data = ['root' => $this->results];
        $data['root']['ResultScan'] = ['link' => $data['root']['ResultScan']];

        return $this->write($filename, Xml::fromArray($data, ['format' => 'tags', 'pretty' => true])->asXML());
    }
}
