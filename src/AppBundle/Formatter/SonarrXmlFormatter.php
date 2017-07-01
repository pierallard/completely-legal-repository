<?php

namespace AppBundle\Formatter;

class SonarrXmlFormatter implements FormatterInterface
{
    /**
     * @param Result[] $results
     *
     * @return string
     */
    function format(array $results)
    {
        $xmlResult = file_get_contents(__DIR__ . '/../Xml/api.xml');
        $xmlResult = str_replace('<!-- results -->', array_reduce(
            $results,
            function ($stack, $result) {
                return $stack . $this->formatResult($result);
            },
            ''
        ), $xmlResult);
        $xmlResult = str_replace('%%count%%', count($results), $xmlResult);

        return $xmlResult;
    }

    /**
     * @param Result $result
     *
     * @return string XML Single result node
     */
    private function formatResult(Result $result)
    {
        $torrentId = $result->getTorrentId();

        try {
            $xmlResult = file_get_contents(__DIR__ . '/../Xml/item.xml');

            $xmlResult = str_replace('%%title%%', $result->getTitle(), $xmlResult);
            $xmlResult = str_replace('%%id%%', $torrentId, $xmlResult);
            $xmlResult = str_replace('%%torrent%%', $result->getDownload(), $xmlResult);
            $xmlResult = str_replace('%%size%%', $result->getSize() * 1024, $xmlResult);
            $xmlResult = str_replace('%%pubDate%%', date('r', time()), $xmlResult);
            $xmlResult = str_replace('%%category%%', '', $xmlResult);
            $xmlResult = str_replace('%%comments%%', '', $xmlResult);
            $xmlResult = str_replace('%%seeders%%', $result->getSeeders(), $xmlResult);
            $xmlResult = str_replace('%%leechers%%', $result->getLeechers(), $xmlResult);

            return $xmlResult;
        } catch (\Exception $e) {
            // TODO Log
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    function match($format)
    {
        return $format === 'sonarr';
    }
}
