<?php
/**
 * Created by PhpStorm.
 * User: pierallard
 * Date: 01/07/17
 * Time: 15:32
 */

namespace AppBundle\Helper;

class RageProvider
{
    /**
     * @param $rageId
     *
     * @return string
     */
    public function getSerieNameFromRageId($rageId)
    {
        $json = file_get_contents('http://api.tvmaze.com/lookup/shows?tvrage=' . $rageId);
        $obj = json_decode($json);

        return $obj->name;
    }
}
