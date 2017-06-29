<?php

namespace AppBundle\Source;

use Symfony\Component\DependencyInjection\ContainerInterface;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDom;

class SourceTpb implements SourceInterface
{
    /** @var string */
    protected $baseUrl;

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

    public function searchTv($query, $rageId, $season, $episode, $offset, $limit, $scheme, $httpPost)
    {
        // TODO: Implement searchTv() method.
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
}
