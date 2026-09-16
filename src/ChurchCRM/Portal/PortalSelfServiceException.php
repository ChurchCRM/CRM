<?php

namespace ChurchCRM\Portal;

use Exception;

/**
 * A Member Portal self-service write the member can fix by changing what they
 * typed: a name that is too short, a date that is not a date, a record the
 * model refused to validate.
 *
 * The HTTP status the route should answer with travels on the exception, and
 * `$failures` carries the per-field messages the form renders inline.
 */
class PortalSelfServiceException extends Exception
{
    /**
     * @param array<int, string> $failures
     */
    public function __construct(string $message, private readonly array $failures = [], int $status = 400)
    {
        parent::__construct($message, $status);
    }

    /**
     * @return array<int, string>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    public function getStatus(): int
    {
        $code = (int) $this->getCode();

        return $code >= 400 && $code < 600 ? $code : 400;
    }
}
