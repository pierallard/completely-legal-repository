<?php

namespace AppBundle\Controller;

use AppBundle\Helper\MetadataProvider;
use AppBundle\Helper\StringCleaner;
use AppBundle\Helper\TrackerRemover;
use GuzzleHttp\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DefaultController extends Controller
{
    /** @var string */
    protected $token;

    /** @var Client */
    protected $t411client;

    /** @var StringCleaner */
    protected $stringCleaner;

    /** @var MetadataProvider */
    protected $metadataProvider;

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
     * @Route("/api", name="api")
     *
     * Sonarr: /api?t=tvsearch&cat=5030,5040&extended=1&apikey=toto&offset=0&limit=100&ep=1&season=2
     */
    public function api(Request $request)
    {
        // Sonarr parameters
        $type = $request->get('t');
        $categories = preg_split('/,/', $request->get('cat'));
        $extended = $request->get('extended') === '1';
        $apikey = $request->get('apikey');
        $offset = intval($request->get('offset'));
        $limit = intval($request->get('limit'));
        $season = $request->get('season');
        $episode = $request->get('ep');
        $rageId = $request->get('rid');
        $query = $request->get('q');

        if ($type === 'tvsearch') {
            $serieName = '';
            if (null !== $rageId) {
                $serieName = $this->getSerieNameFromRageId($rageId);
            } else if (null !== $query) {
                $serieName = $query;
            }
            $result = $this->searchEpisodes($serieName, $season, $episode, $offset, $limit);

            $xmlResults = '';
            $count = 0;

            if (isset($result->torrents)) {
                foreach ($result->torrents as $torrent) {
                    $count++;
                    if (!isset($torrent->isVerified) || ('1' === $torrent->isVerified)) {
                        $xmlResults .= $this->toTorznab($torrent, $request->getScheme() . '://' . $request->getHttpHost());
                    }
                }
            }

            $xmlResult = file_get_contents(__DIR__ . '/../Xml/api.xml');
            $xmlResult = str_replace('<!-- results -->', $xmlResults, $xmlResult);
            $xmlResult = str_replace('%%count%%', $count, $xmlResult);

            $response = new Response($xmlResult);
            $response->headers->set('Content-Type', 'text/xml');

            return $response;
        } else {
            throw new \Exception('Query type not found: "' . $type . '"');
        }
    }

    /**
     * @Route("/", name="home")
     *
     * Couchpotato: /?imdbid=tt1431045&search=Deadpool+2016&user=&passkey=123456
     * https://github.com/CouchPotato/CouchPotatoServer/wiki/CouchPotato-Torrent-Provider
     *
     * {
     *   "results": [
     *     {
     *       "release_name": "Movie.Name.2008.1080p.AC-3.BluRay-ReleaseGroup",
     *       "torrent_id": "123",
     *       "details_url": "http://yourhost.com/torrents.php?id=123",
     *       "download_url": "https://yourhost.com/download.php?ìd=123",
     *       "imdb_id": "tt0123938",
     *       "freeleech": true,
     *       "type": "movie",
     *       "size": 4088,
     *       "leechers": 0,
     *       "seeders": 0
     *     }
     *   ],
     *   "total_results": 4
     * }
     */
    public function home(Request $request)
    {
        $imdbId = $request->get('imdbid');
        $search = $request->get('search');
        $user = $request->get('user');
        $passkey = $request->get('passkey');

        if (null !== $search) {
            $result = $this->searchMovie($search);

            $results = [
                'results' => [],
                'total_results' => 0
            ];

            if (isset($result->torrents)) {
                foreach ($result->torrents as $torrent) {
                    $results['results'][] = $this->toTorrentPotato($torrent, $request->getScheme() . '://' . $request->getHttpHost(), $imdbId);
                    $results['total_results'] = 1;
                }
            }

            $response = new Response(json_encode($results));
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        } else {
            throw new \Exception('Search param required');
        }
    }

    /**
     * @Route("/torrent/{torrentId}", name="torrent")
     */
    public function torrent(Request $request)
    {
        $filename = '/tmp/torrent' . $request->get('torrentId') . '.torrent';
        $file = fopen($filename, 'w');

        $t411Response = $this->getT411Client()->request(
            'GET',
            'torrents/download/' . $request->get('torrentId'), [
                'headers' => ['Authorization' => $this->getToken()],
                'save_to' => $file
            ]
        );
        fclose($file);

        $response = new Response(file_get_contents($this->trackerRemover->removeTracker(
            $filename,
            $request->getScheme() . '://' . $request->getHttpHost()
        )));

        $response->headers->set('Content-Disposition', $t411Response->getHeaders()['Content-Disposition']);
        $response->headers->set('Content-Type', $t411Response->getHeaders()['Content-Type']);

        return $response;
    }

    /**
     * @Route("/tracker/{trackerId}", name="tracker")
     */
    public function tracker(Request $request)
    {
        $parameters = $request->query->all();
        $parameters['downloaded'] = '0';

        $filename = tempnam('/tmp','tracker');
        $file = fopen($filename, 'w');

        $client = new Client(['base_uri' => 'http://t411.download']);
        $client->request(
            'GET',
            sprintf('%s/announce', $request->get('trackerId')), [
                'save_to' => $file,
                'query' => $parameters
            ]
        );

        $response = new Response(file_get_contents($filename));

        return $response;
    }

    /**
     * @param $jsonItem
     *
     * @return string
     */
    protected function toTorznab($jsonItem, $host)
    {
        try {
            $xmlResult = file_get_contents(__DIR__ . '/../Xml/item.xml');
            $xmlResult = str_replace('%%title%%', $this->stringCleaner->cleanStr($jsonItem->name), $xmlResult);
            $xmlResult = str_replace('%%id%%', $jsonItem->id, $xmlResult);
            $xmlResult = str_replace('%%torrent%%', $host . '/torrent/' . $jsonItem->id, $xmlResult);
            $xmlResult = str_replace('%%size%%', $jsonItem->size, $xmlResult);
            $xmlResult = str_replace('%%pubDate%%', $jsonItem->added, $xmlResult);
            $xmlResult = str_replace('%%category%%', $jsonItem->categoryname, $xmlResult);
            $xmlResult = str_replace('%%comments%%', $jsonItem->comments, $xmlResult);
            $xmlResult = str_replace('%%seeders%%', $jsonItem->seeders, $xmlResult);
            $xmlResult = str_replace('%%leechers%%', $jsonItem->leechers, $xmlResult);

            return $xmlResult;
        } catch (\Exception $e) {
            // TODO Log
            return '';
        }
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
                    'username' => $this->getParameter('t411_username'),
                    'password' => $this->getParameter('t411_password'),
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

    protected function searchMovie($search, $offset = 0, $limit = 100)
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
            $this->t411client = new Client(['base_uri' => $this->getParameter('t411_base_url')]);
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
