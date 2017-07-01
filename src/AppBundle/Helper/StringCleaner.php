<?php

namespace AppBundle\Helper;

class StringCleaner
{
    /**
     * @param string $text
     * @param string $tags
     * @param bool $invert
     *
     * @return string
     */
    public function strip_tags_content($text, $tags = '', $invert = FALSE) {

        preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
        $tags = array_unique($tags[1]);

        if (is_array($tags) AND count($tags) > 0) {
            if ($invert == FALSE) {
                return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
            }

            return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
        }
        elseif ($invert == FALSE) {
            return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
        }
        
        return $text;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    public function cleanStr($text)
    {
        return preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $text);
    }
}
