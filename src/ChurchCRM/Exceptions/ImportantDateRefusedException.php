<?php

namespace ChurchCRM\Exceptions;

/**
 * An important date or date type change was refused; the message is the reason,
 * ready to show. A conflict (the change clashes with existing records) is not
 * an input error, so callers can tell them apart.
 */
class ImportantDateRefusedException extends \RuntimeException
{
    public function __construct(string $message, private readonly bool $conflict = false)
    {
        parent::__construct($message);
    }

    public function isConflict(): bool
    {
        return $this->conflict;
    }
}
