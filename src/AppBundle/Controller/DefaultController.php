<?php

namespace AppBundle\Controller;

use AppBundle\Formatter\FormatterRegistry;
use AppBundle\Formatter\SonarrXmlFormatter;
use AppBundle\Formatter\TorznabJsonFormatter;
use AppBundle\Helper\RageProvider;
use AppBundle\Helper\StringCleaner;
use AppBundle\Source\SourceInterface;
use AppBundle\Source\SourceT411;
use AppBundle\Source\SourceTpb;
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

    /** @var FormatterRegistry */
    protected $formatterRegistry;

    /** @var RageProvider */
    protected $rageProvider;

    /**
     * DefaultController constructor.
     */
    public function __construct()
    {
//        $this->source = new SourceT411();
        $this->source = new SourceTpb();
        $this->stringCleaner = new StringCleaner();
        $this->formatterRegistry = new FormatterRegistry();
        $this->rageProvider = new RageProvider();

        $this->formatterRegistry
            ->register(new TorznabJsonFormatter())
            ->register(new SonarrXmlFormatter());
    }

    /**
     * Entry for Sonarr search
     * Example route is: /api?t=tvsearch&cat=5030,5040&extended=1&apikey=toto&offset=0&limit=100&ep=1&season=2&q=preacher
     * Expected result is an XML described in src/AppBundle/Xml/ folder
     *
     * @Route("/api", name="api")
     */
    public function sonarrAction(Request $request)
    {
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

        if ($type === 'tvsearch' || $type === 'caps') {
            $serieName = '';
            if (null !== $rageId) {
                $serieName = $this->rageProvider->getSerieNameFromRageId($rageId);
            } else if (null !== $query) {
                $serieName = $query;
            }

            $this->initSource();
            $results = $this->source->searchTv($serieName, $season, $episode, $offset, $limit, $request->getScheme(), $request->getHttpHost());

            $response = new Response($this->formatterRegistry->get('sonarr')->format($results));
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
    public function couchPotatoAction(Request $request)
    {
        $imdbId = $request->get('imdbid');
        $search = $request->get('search');
        // $user = $request->get('user');
        // $passkey = $request->get('passkey');

        if (null !== $search) {
            $this->initSource();
            $results = $this->source->searchMovie($search, $imdbId, $request->getScheme(), $request->getHttpHost());

            $response = new Response(json_encode($this->formatterRegistry->get('torznab')->format($results)));
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

    private function initSource()
    {
        $this->source->init($this->container);
    }
}
