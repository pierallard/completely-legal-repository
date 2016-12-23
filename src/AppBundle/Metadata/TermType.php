<?php

namespace AppBundle\Metadata;

class TermType
{
    /** @var string */
    protected $id;

    /** @var string */
    protected $name;

    /** @var Term[] */
    protected $terms;

    public function __construct($id, $name)
    {
        $this->id = $id;
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
    public function getId()
    {
        return $this->id;
    }
}
