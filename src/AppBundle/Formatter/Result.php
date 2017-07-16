<?php

namespace AppBundle\Formatter;

class Result
{
    /** @var string */
    protected $torrentId;

    /** @var string */
    protected $title;

    /** @var string */
    protected $download;

    /** @var int */
    protected $size;

    /** @var int */
    protected $leechers;

    /** @var int */
    protected $seeders;

    /** @var string */
    protected $type;

    /**
     * Result constructor.
     * @param string $torrentId
     * @param string $title
     * @param string $download
     * @param int $size,
     * @param int $leechers
     * @param int $seeders
     * @param string $type
     */
    function __construct(
        $torrentId,
        $title,
        $download,
        $size,
        $leechers,
        $seeders,
        $type
    ) {
        $this->torrentId = $torrentId;
        $this->title = $title;
        $this->download = $download;
        $this->size = $size;
        $this->leechers = $leechers;
        $this->seeders = $seeders;
        $this->type = $type;
    }

    /**
     * @return string
     */
    function getTorrentId()
    {
        return $this->torrentId;
    }

    /**
     * @return string
     */
    function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    function getDownload()
    {
        return $this->download;
    }

    /**
     * @return int
     */
    function getSize()
    {
        return $this->size;
    }

    /**
     * @return int
     */
    function getLeechers() {
        return $this->leechers;
    }

    /**
     * @return int
     */
    function getSeeders() {
        return $this->seeders;
    }

    /**
     * @return string
     */
    function getType() {
        return $this->type;
    }
}
