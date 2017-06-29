<?php

namespace AppBundle\Controller;

use AppBundle\Helper\StringCleaner;
use AppBundle\Source\SourceInterface;
use AppBundle\Source\SourceT411;
use GuzzleHttp\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /** @var SourceInterface */
    protected $source;

    /** @var StringCleaner */
    protected $stringCleaner;

    /**
     * DefaultController constructor.
     */
    public function __construct()
    {
        $this->source = new SourceT411();
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
        $offset = intval($request->get('offset'));
        $limit = intval($request->get('limit'));
        $season = $request->get('season');
        $episode = $request->get('ep');
        $rageId = $request->get('rid');
        $query = $request->get('q');

        // $categories = preg_split('/,/', $request->get('cat'));
        // $extended = $request->get('extended') === '1';
        // $apikey = $request->get('apikey');

        if ($type === 'tvsearch') {
            $this->initSource();
            $result = $this->source->searchTv($query, $rageId, $season, $episode, $offset, $limit, $request->getScheme(), $request->getHttpHost());

            $xmlResults = '';
            $count = 0;

            if (isset($result->torrents)) {
                foreach ($result->torrents as $torrent) {
                    if (!isset($torrent->isVerified) || ('1' === $torrent->isVerified)) {
                        $count++;
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
        // $user = $request->get('user');
        // $passkey = $request->get('passkey');

        if (null !== $search) {
            $this->initSource();
            $results = $this->source->searchMovie($search, $imdbId, $request->getScheme(), $request->getHttpHost());
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
        $this->initSource();
        return $this->source->getTorrent($request->get('torrentId'), $request->getScheme(), $request->getHttpHost());
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

        $client = new Client(['base_uri' => $this->getParameter('t411_tracker_url')]);
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

    private function initSource()
    {
        $this->source->init(
            $this->getParameter('t411_base_url'),
            $this->getParameter('t411_username'),
            $this->getParameter('t411_password')
        );
    }
}
