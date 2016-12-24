<?php

namespace AppBundle\Controller;

use AppBundle\Helper\StringCleaner;
use AppBundle\Metadata\Category;
use AppBundle\Metadata\Metadata;
use AppBundle\Metadata\Term;
use AppBundle\Metadata\TermType;
use GuzzleHttp\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    const T411_BASE_URL = "http://api.t411.li";

    /** @var string */
    protected $token;

    /** @var Client */
    protected $t411client;

    /** @var StringCleaner */
    protected $stringCleaner;

    /** @var Metadata */
    protected $metadata;

    /**
     * DefaultController constructor.
     */
    public function __construct()
    {
        $this->stringCleaner = new StringCleaner();
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

        if (($type === 'tvsearch') && (null !== $rageId)) {
            $result = $this->searchEpisodes($this->getSerieNameFromRageId($rageId), $season, $episode, $offset, $limit);

            $xmlResults = '';
            foreach ($result->torrents as $torrent) {
                if (!isset($torrent->isVerified) || ('1' === $torrent->isVerified)) {
                    $xmlResults .= $this->toTorznab($torrent, $request->getScheme() . '://' . $request->getHttpHost());
                }
            }

            $xmlResult = file_get_contents(__DIR__ . '/../Xml/api.xml');
            $xmlResult = str_replace('<!-- results -->', $xmlResults, $xmlResult);
            $xmlResult = str_replace('%%count%%', $result->total, $xmlResult);

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

            foreach ($result->torrents as $torrent) {
                $results['results'][] = $this->toTorrentPotato($torrent, $request->getScheme() . '://' . $request->getHttpHost(), $imdbId);
                $results['total_results'] = 1;
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
        $myFile = fopen($filename, 'w');

        $response = $this->getT411Client()->request(
            'GET',
            'torrents/download/' . $request->get('torrentId'), [
                'headers' => ['Authorization' => $this->getToken()],
                'save_to' => $myFile
            ]
        );
        fclose($myFile);

        $contentType = $response->getHeaders()['Content-Type'];
        $contentDispo = $response->getHeaders()['Content-Disposition'];

        $cmd = "cat " . $filename . "| grep -a -o -E '[0-9]+' |head -n 2";
        $lines = preg_split("/\n/", shell_exec($cmd));
        $first = $lines[0];
        $last = $lines[1];

        $outputFirst = $filename . ".first";
        $outputLast = $filename . ".last";
        $outputMiddle = $filename . ".middle";
        $output = $filename . '.output';

        $offset = intval($first) + 3;
        $cmd = "dd skip=0 count=" . $offset . " if=" . $filename . " of=" . $outputFirst . " bs=1";
        shell_exec($cmd);

        $offset2 = $offset + intval($last) + strlen($last) + 1;
        $cmd = "dd skip=" . $offset2 . " if=" . $filename . " of=" . $outputLast . " bs=1";
        shell_exec($cmd);

        $fakeString = 'http://www.fakewebsite.yol';
        $cmd = 'printf \'' . strlen($fakeString) . ':' . $fakeString . '\' > ' . $outputMiddle;
        shell_exec($cmd);

        $cmd = 'cat ' . $outputFirst . ' ' . $outputMiddle . ' ' . $outputLast . ' > ' . $output;
        shell_exec($cmd);

        $cmd = 'rm ' . $outputFirst . ' ' . $outputMiddle . ' ' . $outputLast;
        shell_exec($cmd);

        $response = new Response(file_get_contents($output));

        $response->headers->set('Content-Disposition', $contentDispo);
        $response->headers->set('Content-Type', $contentType);

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
                    'username' => 'USERNAME',
                    'password' => 'PASSWORD'
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
            $query .= sprintf(
                '&term[%s][]=%s',
                $this->getMetadata()->getSerieSeasonId(),
                $this->getMetadata()->getSerieSeason($seasonNumber)
            );

            if (null !== $episode) {
                $episodeNumber = intval($episode);
                $query .= sprintf(
                    '&term[%s][]=%s',
                    $this->getMetadata()->getSerieEpisodeId(),
                    $this->getMetadata()->getSerieEpisode($episodeNumber)
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
            $this->t411client = new Client(['base_uri' => self::T411_BASE_URL]);
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
     * @return Metadata
     */
    private function getMetadata()
    {
        if (null === $this->metadata) {
            $this->metadata = new Metadata();

            $categoryResponse = $this->getT411Client()->request(
                'GET',
                '/categories/tree/', [
                    'headers' => ['Authorization' => $this->getToken()]
                ]
            );
            $categoryJson = json_decode($categoryResponse->getBody());

            $termResponse = $this->getT411Client()->request(
                'GET',
                '/terms/tree/', [
                    'headers' => ['Authorization' => $this->getToken()]
                ]
            );

            $termJson = json_decode($termResponse->getBody());

            foreach ($categoryJson as $categoryId => $categoryConfig) {
                $name = isset($categoryConfig->name) ? $categoryConfig->name : '';
                $category = new Category($categoryId, $name);
                foreach ($categoryConfig->cats as $subCategoryId => $subCategoryConfig) {
                    $subCategory = new Category($subCategoryId, $subCategoryConfig->name);
                    foreach ($termJson as $termCategoryId => $termTypes) {
                        if ($termCategoryId === $subCategoryId) {
                            foreach ($termTypes as $termTypeId => $termTypeConfig) {
                                $termType = new TermType($termTypeId, $termTypeConfig->type);
                                foreach ($termTypeConfig->terms as $termId => $termName) {
                                    $termType->addTerm(new Term($termId, $termName));
                                }
                                $subCategory->addTermType($termType);
                            }
                        }
                    }
                    $category->addSubCategory($subCategory);
                }
                $this->metadata->addCategory($category);
            }

        }

        return $this->metadata;
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
            "size" => $torrent->size,
            "leechers" => $torrent->leechers,
            "seeders" => $torrent->seeders,
        ];
    }
}
