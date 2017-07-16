<?php

namespace AppBundle\Metadata;

class Category
{
    /** @var string */
    protected $identifier;

    /** @var string */
    protected $name;

    /** @var Category[] */
    protected $subCategories;

    /** @var TermType[] */
    protected $termTypes;

    public function __construct($identifier, $name)
    {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->subCategories = [];
        $this->termTypes = [];
    }

    /**
     * @param Category $subCategory
     */
    public function addSubCategory(Category $subCategory)
    {
        $this->subCategories[] = $subCategory;
    }

    /**
     * @return Category[]
     */
    public function getSubCategories()
    {
        return $this->subCategories;
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param TermType $termType
     */
    public function addTermType(TermType $termType)
    {
        $this->termTypes[] = $termType;
    }

    /**
     * @return TermType[]
     */
    public function getTermsTypes()
    {
        return $this->termTypes;
    }
}
