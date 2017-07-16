<?php

namespace AppBundle\Formatter;

interface FormatterInterface
{
    /**
     * @param Result[] $results
     *
     * @return mixed
     */
    function format(array $results);

    /**
     * @param string $format
     *
     * @return bool
     */
    function match($format);
}
