<?php

namespace ChurchCRM\Exceptions;

/**
 * Thrown before person deletion when a security rule or retained history blocks it.
 */
class PersonDeletionBlockedException extends \RuntimeException
{
}
