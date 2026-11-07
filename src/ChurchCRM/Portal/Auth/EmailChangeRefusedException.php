<?php

namespace ChurchCRM\Portal\Auth;

use RuntimeException;

/**
 * A change or confirmation of a sign-in email that SignInEmailPolicy refused.
 * The message is for the person who asked; the code is the HTTP status.
 */
final class EmailChangeRefusedException extends RuntimeException
{
    public function getStatus(): int
    {
        $code = (int) $this->getCode();

        return $code >= 400 && $code < 600 ? $code : 400;
    }
}
