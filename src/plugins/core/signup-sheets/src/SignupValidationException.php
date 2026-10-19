<?php

namespace ChurchCRM\Plugins\SignupSheets;

/**
 * Raised when submitted signup data fails validation.
 *
 * The message is written for end users and is safe to display — routes surface
 * it directly rather than leaking internal exception detail.
 */
final class SignupValidationException extends \RuntimeException
{
}
