<?php

namespace AppBundle\Helper;

use AppBundle\Metadata\Category;
use AppBundle\Metadata\Metadata;
use AppBundle\Metadata\Term;
use AppBundle\Metadata\TermType;
use GuzzleHttp\Client;

class MetadataProvider
{
    /** @var string */
    protected $categoryContent;

    /** @var string */
    protected $termContent;

    /** @var Metadata */
    protected $metadata;

    /**
     * @param Client $client
     * @param string $token
     *
     * @return Metadata
     */
    public function getMetadata(Client $client, $token)
    {
        if (null === $this->metadata) {
            if (null === $this->categoryContent) {
                $categoryFilePath = __DIR__ . '/../../../var/cache/categories';
                if (!file_exists($categoryFilePath) || (time() - filemtime($categoryFilePath) >= 3600)) {
                    error_log("Regenerating cache file " . $categoryFilePath);

                    $categoryResponse = $client->request(
                        'GET',
                        '/categories/tree/', [
                            'headers' => ['Authorization' => $token]
                        ]
                    );

                    file_put_contents($categoryFilePath, $categoryResponse->getBody());
                }

                $this->categoryContent = file_get_contents($categoryFilePath);
            }

            if (null === $this->termContent) {
                $termFilePath = __DIR__ . '/../../../var/cache/terms';
                if (!file_exists($termFilePath) || (time() - filemtime($termFilePath) >= 3600)) {
                    error_log("Regenerating cache file " . $termFilePath);

                    $termResponse = $client->request(
                        'GET',
                        '/terms/tree/', [
                            'headers' => ['Authorization' => $token]
                        ]
                    );

                    file_put_contents($termFilePath, $termResponse->getBody());
                }

                $this->termContent = file_get_contents($termFilePath);
            }

            $categoryJson = json_decode($this->categoryContent);
            $termJson = json_decode($this->termContent);

            $this->metadata = new Metadata();
            foreach ($categoryJson as $categoryId => $categoryConfig) {
                $name = isset($categoryConfig->name) ? $categoryConfig->name : '';
                $category = new Category($categoryId, $name);
                foreach ($categoryConfig->cats as $subCategoryId => $subCategoryConfig) {
                    $subCategory = new Category($subCategoryId, $subCategoryConfig->name);
                    foreach ($termJson as $termCategoryId => $termTypes) {
                        if ($termCategoryId === $subCategoryId) {
                            foreach ($termTypes as $termTypeId => $termTypeConfig) {
                                $termType = new TermType($termTypeId, $termTypeConfig->type);
                                foreach ($termTypeConfig->terms as $termId => $termName) {
                                    $termType->addTerm(new Term($termId, $termName));
                                }
                                $subCategory->addTermType($termType);
                            }
                        }
                    }
                    $category->addSubCategory($subCategory);
                }
                $this->metadata->addCategory($category);
            }
        }

        return $this->metadata;
    }
}
