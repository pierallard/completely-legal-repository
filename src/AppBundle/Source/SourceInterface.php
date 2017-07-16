<?php

namespace AppBundle\Source;

use AppBundle\Formatter\Result;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

interface SourceInterface
{
    /**
     * @param string $torrentId
     * @param string $sheme
     * @param string $httpPost
     * @return Response
     */
    public function getTorrent($torrentId, $sheme, $httpPost);

    /**
     * @param string $search
     * @param string $imdbId
     * @param string $scheme
     * @param string $httpPost
     *
     * @return Result[]
     */
    public function searchMovie($search, $imdbId, $scheme, $httpPost);

    /**
     * @param $serieName
     * @param $season
     * @param $episode
     * @param $offset
     * @param $limit
     * @param $scheme
     * @param $httpPost
     *
     * @return Result[]
     */
    public function searchTv($serieName, $season, $episode, $offset, $limit, $scheme, $httpPost);

    /**
     * @param ContainerInterface $container
     */
    public function init($container);
}
