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

namespace LinkScanner\Test\TestCase\Utility;

use LinkScanner\TestSuite\TestCase;
use LinkScanner\Utility\BodyParser;
use Tools\TestSuite\ReflectionTrait;

/**
 * BodyParserTest class
 */
class BodyParserTest extends TestCase
{
    use ReflectionTrait;

    /**
     * @test
     * @uses \LinkScanner\Utility\BodyParser::extractLinks()
     */
    public function testExtractLinks(): void
    {
        $extractLinksMethod = fn(string $html): array => (new BodyParser($html, 'http://localhost'))->extractLinks();

        $expected = [
            'http://localhost/page.html',
            'http://localhost/area.htm',
            'http://localhost/file.mp3',
            'http://localhost/embed-video.mp4',
            'http://localhost/frame1.html',
            'http://localhost/frame2.html',
            'http://localhost/pic.jpg',
            'http://localhost/style.css',
            'http://localhost/script.js',
            'http://localhost/file2.mp3',
            'http://localhost/subtitles_en.vtt',
            'http://localhost/movie.mp4',
        ];
        $html = '
<!--suppress HtmlUnknownTarget -->
<a href="/page.html#fragment">Link</a>
<map name="example"><area alt="area" href="area.htm"></map>
<audio src="/file.mp3"></audio>
<embed src="embed-video.mp4">
<!--suppress HtmlDeprecatedAttribute, HtmlDeprecatedTag, HtmlExtraClosingTag, XmlDeprecatedElement -->
<frame src="frame1.html"></frame>
<iframe src="frame2.html"></iframe>
<img alt="pic.jpg" src="pic.jpg" />
<link rel="stylesheet" type="text/css" href="style.css">
<script type="text/javascript" src="script.js" />
<audio><source src="file2.mp3" type="audio/mpeg"></audio>
<video><track src="subtitles_en.vtt"></video>
<video src="//localhost/movie.mp4"></video>';
        $this->assertEquals($expected, $extractLinksMethod($html));

        $html = '<html lang="en"><body>' . $html . '</body></html>';
        $this->assertEquals($expected, $extractLinksMethod($html));

        $html = '<b>No links here!</b>';
        $this->assertEquals([], $extractLinksMethod($html));

        $html = '<a href="page.html">Link</a>' . PHP_EOL . '<a href="http://localhost/page.html">Link</a>';
        $expected = ['http://localhost/page.html'];
        $this->assertEquals($expected, $extractLinksMethod($html));

        //Checks that the result is the same as that saved in the
        //  `extractedLinks` property as a cache
        $expected = ['link.html'];
        $BodyParser = new BodyParser($html, 'http://localhost');
        $this->setProperty($BodyParser, 'extractedLinks', $expected);
        $this->assertEquals($expected, $BodyParser->extractLinks());

        //No HTML
        $this->assertEquals([], $extractLinksMethod('no html'));
    }
}
