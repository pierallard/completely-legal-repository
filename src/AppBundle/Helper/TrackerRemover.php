<?php

namespace AppBundle\Helper;

class TrackerRemover
{
    /**
     * Removes the tracker from a .torrent file and generated a new file
     *
     * @param $filename
     * @param $host
     * @return string
     */
    public function removeTracker($filename, $host)
    {
        $output = $filename . '.output';

        $beginFilename = $this->generateBegin($filename);
        $endFilename = $this->generateEnd($filename);
        $middleFilename = $this->generateMiddle($filename, $host);

        $cmdCat = 'cat ' . $beginFilename . ' ' . $middleFilename . ' ' . $endFilename . ' > ' . $output;
        shell_exec($cmdCat);

        $cmdRm = 'rm ' . $beginFilename . ' ' . $middleFilename . ' ' . $endFilename;
        shell_exec($cmdRm);

        return $output;
    }

    /**
     * Returns the 2 first offsets of the parts of the torrent
     * For example, the file content
     * d8:announce62:http://t411.download/xxxxxx/announce7:comment...
     * will return
     * ["8", "62"]
     *
     * @param $filename
     * @return string[]
     */
    protected function getOffsets($filename)
    {
        $cmd = "cat " . $filename . "| grep -a -o -E '[0-9]+' |head -n 2";
        $lines = preg_split("/\n/", shell_exec($cmd));

        return [$lines[0], $lines[1]];
    }

    /***
     * Generates a file containing the begin of the file
     * The content should look like "d8:announce"
     *
     * @param $filename
     * @return string
     */
    protected function generateBegin($filename)
    {
        $outputFirst = $filename . ".first";
        $offset = intval($this->getOffsets($filename)[0]) + 3;
        $cmd = "dd skip=0 count=" . $offset . " if=" . $filename . " of=" . $outputFirst . " bs=1";
        shell_exec($cmd);

        return $outputFirst;
    }

    /**
     * Generates a file containing the end of the file
     * The content should look like "7:comment...."
     *
     * @param $filename
     * @return string
     */
    protected function generateEnd($filename)
    {
        $outputLast = $filename . ".last";
        $offset =
            intval($this->getOffsets($filename)[0]) + 3 +
            intval($this->getOffsets($filename)[1]) + strlen($this->getOffsets($filename)[1]);
        $cmd = "dd skip=" . $offset . " if=" . $filename . " of=" . $outputLast . " bs=1";
        shell_exec($cmd);

        return $outputLast;
    }

    /**
     * Returns the tracker identifier of a torrent file
     * For example, if the file begins with
     * d8:announce62:http://t411.download/xxxxxxxx/announce7:comment...
     *
     * Then the result will be "xxxxxx"
     *
     * @param $filename
     * @return mixed
     */
    protected function getTrackerIdentifier($filename)
    {
        $trackerPart = substr(
            file_get_contents($filename),
            intval($this->getOffsets($filename)[0]) + 3,
            intval($this->getOffsets($filename)[1]) + 3
        );

        $parts = preg_split('/\//', $trackerPart);

        return $parts[count($parts) - 2];
    }

    /**
     * Generates a file containing a fake URL for tracker
     *
     * @param $filename
     * @param $host
     * @return string
     */
    protected function generateMiddle($filename, $host)
    {
        $fakeOutputMiddle = $filename . ".middle";
        $fakeString = $host . '/tracker/' . $this->getTrackerIdentifier($filename);
        $cmd = 'printf \'' . strlen($fakeString) . ':' . $fakeString . '\' > ' . $fakeOutputMiddle;
        shell_exec($cmd);

        return $fakeOutputMiddle;
    }
}
