<?php

namespace AppBundle\Source;

use AppBundle\Formatter\Result;
use AppBundle\Helper\StringCleaner;
use AppBundle\Helper\URLify;
use Symfony\Component\DependencyInjection\ContainerInterface;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDom;

class SourceTpb implements SourceInterface
{
    /** @var string */
    protected $baseUrl;

    /** @var StringCleaner */
    protected $stringCleaner;

    /** @var URLify */
    protected $urlify;

    function __construct()
    {
        $this->stringCleaner = new StringCleaner();
        $this->urlify = new URLify();
    }

    public function getTorrent($torrentId, $sheme, $httpPost)
    {
        // TODO: Implement getTorrent() method.
    }

    /**
     * {@inheritdoc}
     */
    public function searchMovie($search, $imdbId, $scheme, $httpPost)
    {
        $query = $this->baseUrl . '/search/' . $this->urlify->filter($search) . '/0/99/200';

        return $this->parseQuery($query, $httpPost);
    }

    /**
     * {@inheritdoc}
     */
    public function searchTv($serieName, $season, $episode, $offset, $limit, $scheme, $httpPost)
    {
        $query = $this->baseUrl . '/search/' . $this->urlify->filter(
            $this->getTvSearchQuery($serieName, $season, $episode)
            ) . '/0/99/200';

        return $this->parseQuery($query, $httpPost);
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
    private function parseSize($torrent)
    {
        $description = $torrent->find('.detDesc', 0)->innerHtml;
        $matches = null;
        preg_match('/Size (?<number>[0-9\.]+)&nbsp;(?<unit>[MGk])iB,/', $description, $matches);

        $number = floatval($matches['number']);
        if ('M' === $matches['unit']) {
            $number = $number * 1000 * 1000;
        } else if ('G' === $matches['unit']) {
            $number = $number * 1000 * 1000 * 1000;
        }

        return intval($number);
    }

    /**
     * @param string $query
     *
     * @return Result[]
     */
    private function parseQuery($query, $httpPost)
    {
        $htmlStr = file_get_contents($query);
        $html = HtmlDomParser::str_get_html($htmlStr);

        return array_map(function (SimpleHtmlDom $torrent) use ($httpPost) {
            $torrentId = preg_split('/\//', $torrent->find('.detLink', 0)->getAttribute('href'))[2];
            return new Result(
                $torrentId,
                $torrent->find('.detLink',0)->innerHtml,
                $httpPost . '/torrent/' . $torrentId,
                $this->parseSize($torrent),
                intval($torrent->find('td', 3)->innerHtml),
                intval($torrent->find('td', 2)->innerHtml)
            );
        }, (array) $html->find('#searchResult tr:not(.header)'));
    }

    /**
     * @param $serieName
     * @param $season
     * @param $episode
     *
     * @return string
     */
    private function getTvSearchQuery($serieName, $season, $episode)
    {
        $search = $serieName;
        if (null !== $season) {
            if (null !== $episode) {
                return sprintf('%s s%02de%02d', $search, $season, $episode);
            }
            return sprintf('%s season %s', $search, $season);
        }

        return $search;
    }
}

