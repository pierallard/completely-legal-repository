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

            $xmlResult = str_replace('%%title%%', $this->formatValue($result->getTitle()), $xmlResult);
            $xmlResult = str_replace('%%id%%', $this->formatValue($torrentId), $xmlResult);
            $xmlResult = str_replace('%%torrent%%', $this->formatValue($result->getDownload()), $xmlResult);
            $xmlResult = str_replace('%%size%%', $this->formatValue($result->getSize()), $xmlResult);
            $xmlResult = str_replace('%%pubDate%%', $this->formatValue(date('r', time())), $xmlResult);
            $xmlResult = str_replace('%%category%%', $this->formatValue(''), $xmlResult);
            $xmlResult = str_replace('%%comments%%', $this->formatValue(''), $xmlResult);
            $xmlResult = str_replace('%%seeders%%', $this->formatValue($result->getSeeders()), $xmlResult);
            $xmlResult = str_replace('%%leechers%%', $this->formatValue($result->getLeechers()), $xmlResult);
            $xmlResult = str_replace('%%type%%', $this->formatValue($result->getType()), $xmlResult);

            return $xmlResult;
        } catch (\Exception $e) {
            // TODO Log
            return '';
        }
    }

    /**
     * @param string $string
     * @return string
     */
    private function formatValue($string)
    {
        return str_replace('&', '&amp;', $string);
    }

    /**
     * {@inheritdoc}
     */
    function match($format)
    {
        return $format === 'sonarr';
    }
}
