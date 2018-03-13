<?php
/**
 * Created by PhpStorm.
 * User: klepov.e
 * Date: 13.03.2018
 * Time: 20:02
 */

namespace Alva\CsvToSql;

class Reader
{
    protected $file;

    /**
     * Convert constructor.
     *
     * @param        $filename
     * @param string $mode
     *
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public function __construct(string $filename, string $mode = "r")
    {
        if (!is_file($filename)) {
            throw new \RuntimeException("File not found");
        }

        $this->file = new \SplFileObject($filename, $mode);
    }

    protected function iterateText()
    {
        $count = 0;
        while (!$this->file->eof()) {
            yield $this->file->fgets();
            $count++;
        }

        return $count;
    }

    protected function iterateBinary($bytes)
    {
        $count = 0;

        while (!$this->file->eof()) {
            yield $this->file->fread($bytes);
            $count++;
        }
    }

    /**
     * @param string $type
     * @param null   $bytes
     *
     * @return \NoRewindIterator
     */
    public function iterate(string $type = "Text", $bytes = NULL): \NoRewindIterator
    {
        if ($type === "Text") {
            return new \NoRewindIterator($this->iterateText());
        }

        return new \NoRewindIterator($this->iterateBinary($bytes));
    }
}