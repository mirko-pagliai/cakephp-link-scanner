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
namespace LinkScanner\Utility;

use phpUri;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A body parser.
 *
 * It can tell if a body contains HTML code and can extract links from body.
 * @since 1.1.17
 */
class BodyParser
{
    /**
     * Body
     * @var string
     */
    protected string $body;

    /**
     * Extracted links. This property works as a cache of values
     * @var array<string>
     */
    protected array $extractedLinks = [];

    /**
     * HTML tags that may contain links and therefore need to be scanned.
     *
     * Array with tag names as keys and attribute names as values.
     * @var array
     */
    protected const TAGS = [
        'a' => 'href',
        'area' => 'href',
        'audio' => 'src',
        'embed' => 'src',
        'frame' => 'src',
        'iframe' => 'src',
        'img' => 'src',
        'link' => 'href',
        'script' => 'src',
        'source' => 'src',
        'track' => 'src',
        'video' => 'src',
    ];

    /**
     * Reference url. Used to determine the relative links
     * @var string
     */
    protected string $url;

    /**
     * Constructor
     * @param string|\Psr\Http\Message\StreamInterface $body Body
     * @param string $url Reference url. Used to determine the relative links
     */
    public function __construct(StreamInterface|string $body, string $url)
    {
        $this->body = (string)$body;
        $this->url = $url;
    }

    /**
     * Internal method to build an absolute url
     * @param string $relative Relative url to join
     * @param string $base Base path, on which to construct the absolute url
     * @return string
     * @since 1.1.18
     */
    protected function urlToAbsolute(string $relative, string $base): string
    {
        $base = clean_url($base, false, true);
        $base = preg_match('/^(\w+:\/\/.+)\/[^.\/]+\.[^.\/]+$/', $base, $matches) ? $matches[1] : $base;

        return phpUri::parse($base . '/')->join($relative);
    }

    /**
     * Extracts links from body
     * @return array<string> Array of links
     */
    public function extractLinks(): array
    {
        if ($this->extractedLinks) {
            return $this->extractedLinks;
        }

        //Checks the body contains html code
        if (strcasecmp($this->body, strip_tags($this->body)) == 0) {
            return [];
        }

        $crawler = new Crawler($this->body);

        $extractedLinks = [];
        foreach (self::TAGS as $tag => $attribute) {
            foreach ($crawler->filterXPath('//' . $tag)->extract([$attribute]) as $link) {
                if ($link) {
                    $extractedLinks[] = clean_url($this->urlToAbsolute($link, $this->url), true, true);
                }
            }
        }

        return $this->extractedLinks = array_unique($extractedLinks);
    }
}
