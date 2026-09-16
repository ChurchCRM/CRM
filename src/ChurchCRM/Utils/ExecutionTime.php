<?php

namespace ChurchCRM\Utils;

class ExecutionTime
{
    // inspired from https://stackoverflow.com/a/22885011
    private float $startTime;
    private ?float $endTime = null;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function end(): void
    {
        $this->endTime = microtime(true);
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

    public function __toString(): string
    {
        if ($this->endTime === null) {
            return 'This process is still running: ' . $this->getMilliseconds() . ' ms.';
        }

        return 'This process completed in ' . $this->getMilliseconds() . ' ms.';
    }
}
