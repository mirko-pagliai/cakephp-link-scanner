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
    public function __construct($body, string $url)
    {
        $this->body = (string)$body;
        $this->url = $url;
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

        if (!is_html($this->body)) {
            return [];
        }

        $crawler = new Crawler($this->body);

        $links = [];
        foreach (self::TAGS as $tag => $attribute) {
            foreach ($crawler->filterXPath('//' . $tag)->extract([$attribute]) as $link) {
                if ($link) {
                    $links[] = clean_url(url_to_absolute($this->url, $link), true, true);
                }
            }
        }

        return $this->extractedLinks = array_unique($links);
    }
}
