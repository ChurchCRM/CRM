<?php

namespace ChurchCRM\Exceptions;

/**
 * Thrown when deleting a person would remove a login the current user may not remove.
 * The message is the user-facing reason from Person::getLoginDeletionBlockedReason().
 */
class PersonDeletionBlockedException extends \RuntimeException
{
}
