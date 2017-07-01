<?php

namespace AppBundle\Source;

use AppBundle\Helper\StringCleaner;
use Symfony\Component\DependencyInjection\ContainerInterface;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDom;

class SourceTpb implements SourceInterface
{
    /** @var string */
    protected $baseUrl;

    /** @var StringCleaner */
    protected $stringCleaner;

    function __construct()
    {
        $this->stringCleaner = new StringCleaner();
    }

    public function getTorrent($torrentId, $sheme, $httpPost)
    {
        // TODO: Implement getTorrent() method.
    }

    public function searchMovie($search, $imdbId, $scheme, $httpPost)
    {
        $query = $this->baseUrl . '/search/' . \URLify::filter($search) . '/0/99/200';

        $htmlStr = file_get_contents($query);
        $html = HtmlDomParser::str_get_html($htmlStr);

        $results = [
            'results' => [],
            'total_results' => 0
        ];

        foreach ($html->find('#searchResult tr:not(.header)') as $torrent) {
            $results['results'][] = $this->toTorrentPotato($torrent, $scheme . '://' . $httpPost);
            $results['total_results'] += 1;
        }

        return $results;
    }

    public function searchTv($serieName, $season, $episode, $offset, $limit, $scheme, $httpPost)
    {
        $search = $serieName;
        if (null !== $season) {
            if (null !== $episode) {
                $search = sprintf('%s s%02de%02d', $search, $season, $episode);
            } else {
                $search = sprintf('%s season %s', $search, $season);
            }
        }

        $query = $this->baseUrl . '/search/' . \URLify::filter($search) . '/0/99/200';

        $htmlStr = file_get_contents($query);
        $html = HtmlDomParser::str_get_html($htmlStr);

        $xmlResults = '';
        $count = 0;

//        echo $html;
//        die();

        foreach ($html->find('#searchResult tr:not(.header)') as $torrent) {
            if ('' === $torrent->find('td', 0)->getAttribute('colspan')) {
                $count++;
                $xmlResults .= $this->toTorznab($torrent, $scheme . '://' . $httpPost);
            }
        }

        $xmlResult = file_get_contents(__DIR__ . '/../Xml/api.xml');
        $xmlResult = str_replace('<!-- results -->', $xmlResults, $xmlResult);
        $xmlResult = str_replace('%%count%%', $count, $xmlResult);

        return $xmlResult;
    }

    /**
     * @param ContainerInterface $container
     */
    public function init($container)
    {
        $this->baseUrl = $container->getParameter('tpb_base_url');
    }


    /**
     * @param SimpleHtmlDom $torrent
     *
     * @return integer
     */
    private function parseSizeMb($torrent)
    {
        $description = $torrent->find('.detDesc', 0)->innerHtml;
        $matches = null;
        preg_match('/Size (?<number>[0-9\.]+)&nbsp;(?<unit>[MGk])iB,/', $description, $matches);

        $number = floatval($matches['number']);
        if ('M' === $matches['unit']) {
            $number = $number * 1000;
        } else if ('G' === $matches['unit']) {
            $number = $number * 1000 * 1000;
        }

        return intval($number);
    }

    /**
     * @param SimpleHtmlDom $torrent
     *
     * @return array
     */
    private function toTorrentPotato($torrent, $host)
    {
        $torrentId = preg_split('/\//', $torrent->find('.detLink', 0)->getAttribute('href'))[2];

        return [
            "release_name" => $torrent->find('.detLink',0)->innerHtml,
            "torrent_id" => $torrentId,
            "details_url" => $host . '/torrent/' . $torrentId,
            "download_url" => $host . '/torrent/' . $torrentId,
            "imdb_id" => '',
            "freeleech" => true,
            "type" => "movie",
            "size" => $this->parseSizeMb($torrent),
            "leechers" => intval($torrent->find('td', 3)->innerHtml),
            "seeders" => intval($torrent->find('td', 2)->innerHtml)
        ];
    }

    /**
     * @param $torrent
     *
     * @return string
     */
    protected function toTorznab($torrent, $host)
    {
        $torrentId = preg_split('/\//', $torrent->find('.detLink', 0)->getAttribute('href'))[2];

//        try {
            $xmlResult = file_get_contents(__DIR__ . '/../Xml/item.xml');
            $xmlResult = str_replace('%%title%%', $torrent->find('.detLink',0)->innerHtml, $xmlResult);
            $xmlResult = str_replace('%%id%%', $torrentId, $xmlResult);
            $xmlResult = str_replace('%%torrent%%', $host . '/torrent/' . $torrentId, $xmlResult);
            $xmlResult = str_replace('%%size%%', $this->parseSizeMb($torrent) * 1024, $xmlResult);
            $xmlResult = str_replace('%%pubDate%%', date('r', time()), $xmlResult);
            $xmlResult = str_replace('%%category%%', '', $xmlResult);
            $xmlResult = str_replace('%%comments%%', '', $xmlResult);
            $xmlResult = str_replace('%%seeders%%', intval($torrent->find('td', 2)->innerHtml), $xmlResult);
            $xmlResult = str_replace('%%leechers%%', intval($torrent->find('td', 3)->innerHtml), $xmlResult);

            return $xmlResult;
//        } catch (\Exception $e) {
//            // TODO Log
//            return '';
//        }
    }
}

