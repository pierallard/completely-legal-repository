<?php

namespace AppBundle\Metadata;

class Term
{
    /** @var string */
    protected $identifier;

    /** @var string */
    protected $name;

    public function __construct($identifier, $name)
    {
        $this->identifier = $identifier;
        $this->name = $name;
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
