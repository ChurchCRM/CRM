<?php

namespace ChurchCRM\Exceptions;

/**
 * Thrown when a DonationFund with the requested ID does not exist.
 *
 * Using a dedicated type lets route handlers distinguish a 404 (fund not found)
 * from a 400 (bad input / duplicate name) without inspecting exception messages.
 */
class DonationFundNotFoundException extends \InvalidArgumentException
{
}
