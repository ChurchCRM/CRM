<?php

namespace ChurchCRM\Utils;

class ExecutionTime
{
    // inspired from https://stackoverflow.com/a/22885011
    private $startTime;
    private $endTime;
    private $startR;
    private $endR;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startR = getrusage();
    }

    public function end(): void
    {
        $this->endTime = microtime(true);
        $this->endR = getrusage();
    }

    public function getMilliseconds(): float
    {
        // if End() has not yet been called, this returns the current number of running seconds.
        // Otherwise, returns the ending number of seconds
        if ($this->endTime === null) {
            $value = (microtime(true) - $this->startTime) * 1000;
        } else {
            $value = ($this->endTime - $this->startTime) * 1000;
        }

        return round($value, 2);
    }

    private function runTime(array $ru, array $rus, string $index)
    {
        return ($ru["ru_$index.tv_sec"] * 1000 + intval($ru["ru_$index.tv_usec"] / 1000))
        - ($rus["ru_$index.tv_sec"] * 1000 + intval($rus["ru_$index.tv_usec"] / 1000));
    }

    public function __toString(): string
    {
        return 'This process used ' . $this->runTime($this->endTime, $this->startTime, 'utime') .
        " ms for its computations\nIt spent " . $this->runTime($this->endTime, $this->startTime, 'stime') .
        " ms in system calls\n";
    }
}
