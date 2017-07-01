<?php

namespace AppBundle\Formatter;

class FormatterRegistry
{
    /** @var FormatterInterface[] */
    protected $formatters;
    /**
     * FormatterRegistry constructor.
     */
    function __construct()
    {
        $this->formatters = [];
    }

    /**
     * @param FormatterInterface $formatter
     *
     * @return FormatterRegistry
     */
    function register(FormatterInterface $formatter) {
        $this->formatters[] = $formatter;

        return $this;
    }

    /**
     * @param string $format
     *
     * @return FormatterInterface|null
     */
    function get($format) {
        foreach($this->formatters as $formatter) {
            if ($formatter->match($format)) {
                return $formatter;
            }
        }

        return null;
    }
}
