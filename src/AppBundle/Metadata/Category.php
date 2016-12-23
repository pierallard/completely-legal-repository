<?php

namespace AppBundle\Metadata;

class Category
{
    /** @var string */
    protected $id;

    /** @var string */
    protected $name;

    /** @var Category[] */
    protected $subCategories;

    /** @var TermType[] */
    protected $termTypes;

    public function __construct($id, $name)
    {
        $this->id = $id;
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
    public function getId()
    {
        return $this->id;
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
