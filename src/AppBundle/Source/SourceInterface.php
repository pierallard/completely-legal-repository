<?php

namespace AppBundle\Source;

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
     * @return array
     */
    public function searchMovie($search, $imdbId, $scheme, $httpPost);

    public function searchTv($query, $rageId, $season, $episode, $offset, $limit, $scheme, $httpPost);

    public function init($baseUrl, $username, $password);
}
