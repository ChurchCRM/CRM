<?php

namespace ChurchCRM\Exceptions;

/**
 * Core refused to delete an event (people still checked in, a kiosk assigned to it);
 * the message is the reason, ready to show.
 */
class EventDeleteRefusedException extends \RuntimeException
{
}
