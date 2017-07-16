<?php

namespace AppBundle\Metadata;

class TermType
{
    /** @var string */
    protected $identifier;

    /** @var string */
    protected $name;

    /** @var Term[] */
    protected $terms;

    /**
     * TermType constructor.
     *
     * @param $identifier
     * @param $name
     */
    public function __construct($identifier, $name)
    {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->terms = [];
    }

    /**
     * @param Term $term
     */
    public function addTerm(Term $term)
    {
        $this->terms[] = $term;
    }

    /**
     * @return Term[]
     */
    public function getTerms()
    {
        return $this->terms;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }
}
