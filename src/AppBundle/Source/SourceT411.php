<?php

namespace AppBundle\Source;

use AppBundle\Helper\MetadataProvider;
use AppBundle\Helper\StringCleaner;
use AppBundle\Helper\TrackerRemover;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class SourceT411 implements SourceInterface
{
    /** @var StringCleaner */
    protected $stringCleaner;

    /** @var MetadataProvider */
    protected $metadataProvider;

    /** @var TrackerRemover */
    protected $trackerRemover;

    /** @var string */
    protected $token;

    /** @var Client */
    protected $t411client;

    protected $baseUrl, $username, $password;

    /**
     * DefaultController constructor.
     */
    public function __construct()
    {
        $this->stringCleaner = new StringCleaner();
        $this->trackerRemover = new TrackerRemover();
        $this->metadataProvider = new MetadataProvider();
    }

    /**
     * @param ContainerInterface $container
     */
    public function init($container)
    {
        $this->baseUrl  = $container->getParameter('t411_base_url');
        $this->username = $container->getParameter('t411_username');
        $this->password = $container->getParameter('t411_password');
    }

    public function searchMovie($search, $imdbId, $scheme, $httpPost)
    {
        $result = $this->searchMovieInternal($search);

        $results = [
            'results' => [],
            'total_results' => 0
        ];

        if (isset($result->torrents)) {
            foreach ($result->torrents as $torrent) {
                $results['results'][] = $this->toTorrentPotato($torrent, $scheme . '://' . $httpPost, $imdbId);
                $results['total_results'] += 1;
            }
        }

        return $results;
    }

    public function searchTv($query, $rageId, $season, $episode, $offset, $limit, $scheme, $httpPost)
    {
        $serieName = '';
        if (null !== $rageId) {
            $serieName = $this->getSerieNameFromRageId($rageId);
        } else if (null !== $query) {
            $serieName = $query;
        }
        return $this->searchEpisodes($serieName, $season, $episode, $offset, $limit);
    }

    public function getTorrent($torrentId, $sheme, $httpPost)
    {
        $filename = '/tmp/torrent' . $torrentId . '.torrent';
        $file = fopen($filename, 'w');

        $t411Response = $this->getT411Client()->request(
            'GET',
            'torrents/download/' . $torrentId, [
                'headers' => ['Authorization' => $this->getToken()],
                'save_to' => $file
            ]
        );
        fclose($file);

        $response = new Response(file_get_contents($this->trackerRemover->removeTracker(
            $filename,
            $sheme . '://' . $httpPost
        )));

        $response->headers->set('Content-Disposition', $t411Response->getHeaders()['Content-Disposition']);
        $response->headers->set('Content-Type', $t411Response->getHeaders()['Content-Type']);

        return $response;
    }

    /**
     * @return bool
     *
     * @throws \Exception
     */
    protected function login()
    {
        $response = $this->getT411Client()->request(
            'POST',
            '/auth', [
                'form_params' => [
                    'username' => $this->username,
                    'password' => $this->password,
                ]
            ]
        );

        $json = json_decode($response->getBody());

        if (isset($json->error)) {
            throw new \Exception('Error on login. Code:' . $json->code);
        }

        $token = $json->token;
        $this->token = $token;

        return true;
    }

    /**
     * Example of result:
     * {
     *     "query":null,
     *     "offset":"0",
     *     "limit":"100",
     *     "total":"300000",
     *     "torrents": [
     *         {
     *             "id": "5623202",
     *             "name":"Campagne D\u00e9coration N 103 Janvier-F\u00e9vrier 2017 PDF",
     *             "category":"410",
     *             "rewritename":"campagne-d-coration-n-103-janvier-f-vrier-2017-pdf",
     *             "seeders":"3",
     *             "leechers":"2",
     *             "comments":"0",
     *             "isVerified":"0",
     *             "added":"2016-12-22 12:51:57",
     *             "size":"107416218",
     *             "times_completed":"2",
     *             "owner":"6402408",
     *             "categoryname":"Presse",
     *             "categoryimage":"ebook-press",
     *             "username":"guerrierdelanuit",
     *             "privacy":"normal"
     *         }
     *     ]
     * }
     *
     * @return array
     */
    protected function searchEpisodes($query, $season, $episode, $offset = 0, $limit = 100)
    {
        $query = '/torrents/search/' . \URLify::filter($query) . '?offset=' . $offset . '&limit=' . $limit;

        if (null !== $season) {
            $seasonNumber = intval($season);
            $metadata = $this->metadataProvider->getMetadata($this->getT411Client(), $this->getToken());
            $query .= sprintf(
                '&term[%s][]=%s',
                $metadata->getSerieSeasonId(),
                $metadata->getSerieSeason($seasonNumber)
            );

            if (null !== $episode) {
                $episodeNumber = intval($episode);
                $query .= sprintf(
                    '&term[%s][]=%s',
                    $metadata->getSerieEpisodeId(),
                    $metadata->getSerieEpisode($episodeNumber)
                );
            }
        }

        return $this->queryFiles($query);
    }

    protected function searchMovieInternal($search, $offset = 0, $limit = 100)
    {
        $query = '/torrents/search/' . \URLify::filter($search) . '?offset=' . $offset . '&limit=' . $limit;

        return $this->queryFiles($query);
    }

    protected function queryFiles($query)
    {
        $response = $this->getT411Client()->request(
            'GET',
            $query, [
                'headers' => ['Authorization' => $this->getToken()]
            ]
        );

        $contents = $response->getBody()->getContents();
        $realContent = $this->stringCleaner->strip_tags_content($contents);
        $json = json_decode($realContent);

        return $json;
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    protected function getToken()
    {
        if (null === $this->token) {
            $this->login();
        }

        return $this->token;
    }

    /**
     * @return Client
     */
    protected function getT411Client()
    {
        if (null === $this->t411client) {
            $this->t411client = new Client(['base_uri' => $this->baseUrl]);
        }

        return $this->t411client;
    }

    /**
     * @param $rageId
     *
     * @return string
     */
    protected function getSerieNameFromRageId($rageId)
    {
        $json = file_get_contents('http://api.tvmaze.com/lookup/shows?tvrage=' . $rageId);
        $obj = json_decode($json);

        return $obj->name;
    }

    /**
     * @param $torrent
     *
     * @return array
     */
    private function toTorrentPotato($torrent, $host, $imdbid)
    {
        return [
            "release_name" => $torrent->rewritename,
            "torrent_id" => $torrent->id,
            "details_url" => $host . '/torrent/' . $torrent->id,
            "download_url" => $host . '/torrent/' . $torrent->id,
            "imdb_id" => $imdbid,
            "freeleech" => true,
            "type" => "movie",
            "size" => intval(intval($torrent->size) / (1024 * 1024)),
            "leechers" => $torrent->leechers,
            "seeders" => $torrent->seeders,
        ];
    }
}
