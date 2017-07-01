<?php

namespace AppBundle\Metadata;

class Metadata
{
    const CATEGORY_SERIE = "Série TV";
    const CATEGORY_MOVIE = "Film";
    const TERMTYPE_SEASON = "SérieTV - Saison";
    const TERM_SEASON = "Saison %02d";
    const TERMTYPE_EPISODE = "SérieTV - Episode";
    const TERM_EPISODE = "Episode %02d";

    /** @var Category[] */
    protected $categories;

    public function __construct()
    {
        $this->categories = [];
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    public function getSerieSeasonId()
    {
        foreach ($this->categories as $category) {
            foreach ($category->getSubCategories() as $subCategory) {
                if (self::CATEGORY_SERIE === $subCategory->getName()) {
                    foreach ($subCategory->getTermsTypes() as $termType) {
                        if (self::TERMTYPE_SEASON === $termType->getName()) {
                            return $termType->getIdentifier();
                        }
                    }
                }
            }
        }

        throw new \Exception('Can not found term type ' . self::TERMTYPE_SEASON);
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    public function getSerieEpisodeId()
    {
        foreach ($this->categories as $category) {
            foreach ($category->getSubCategories() as $subCategory) {
                if (self::CATEGORY_SERIE === $subCategory->getName()) {
                    foreach ($subCategory->getTermsTypes() as $termType) {
                        if (self::TERMTYPE_EPISODE === $termType->getName()) {
                            return $termType->getIdentifier();
                        }
                    }
                }
            }
        }

        throw new \Exception('Can not found term type ' . self::TERMTYPE_EPISODE);
    }

    /**
     * @param $seasonNumber
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getSerieSeason($seasonNumber)
    {
        foreach ($this->categories as $category) {
            foreach ($category->getSubCategories() as $subCategory) {
                if (self::CATEGORY_SERIE === $subCategory->getName()) {
                    foreach ($subCategory->getTermsTypes() as $termType) {
                        if (self::TERMTYPE_SEASON === $termType->getName()) {
                            foreach ($termType->getTerms() as $term) {
                                if (sprintf(self::TERM_SEASON, $seasonNumber) === $term->getName()) {
                                    return $term->getIdentifier();
                                }
                            }
                        }
                    }
                }
            }
        }

        throw new \Exception('Can not found season ' . $seasonNumber . ' in terms');
    }

    /**
     * @param $episodeNumber
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getSerieEpisode($episodeNumber)
    {
        foreach ($this->categories as $category) {
            foreach ($category->getSubCategories() as $subCategory) {
                if (self::CATEGORY_SERIE === $subCategory->getName()) {
                    foreach ($subCategory->getTermsTypes() as $termType) {
                        if (self::TERMTYPE_EPISODE === $termType->getName()) {
                            foreach ($termType->getTerms() as $term) {
                                if (sprintf(self::TERM_EPISODE, $episodeNumber) === $term->getName()) {
                                    return $term->getIdentifier();
                                }
                            }
                        }
                    }
                }
            }
        }

        throw new \Exception('Can not found episode ' . $episodeNumber . ' in terms');
    }

    /**
     * @param Category $category
     */
    public function addCategory(Category $category)
    {
        $this->categories[] = $category;
    }
}
