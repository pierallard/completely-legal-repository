<?php

namespace AppBundle\Formatter;

class TorznabJsonFormatter implements FormatterInterface
{
    /**
     * @param Result[] $results
     *
     * @return array
     */
    public function format(array $results) {
        return [
            'results' => array_map(
                function ($result) {
                    return $this->formatResult($result);
                },
                $results
            ),
            'total_results' => count($results)
        ];
    }

    /**
     * @param Result $result
     *
     * @return array
     */
    private function formatResult($result)
    {
        return [
            "release_name" => $result->getTitle(),
            "torrent_id" => $result->getTorrentId(),
            "details_url" => $result->getDownload(),
            "download_url" => $result->getDownload(),
            "imdb_id" => '',
            "freeleech" => true,
            "type" => "movie",
            "size" => $result->getSize() * 1024 * 1024,
            "leechers" => $result->getLeechers(),
            "seeders" => $result->getSeeders()
        ];
    }

    /**
     * {@inheritdoc}
     */
    function match($format)
    {
        return $format === 'torznab';
    }
}
