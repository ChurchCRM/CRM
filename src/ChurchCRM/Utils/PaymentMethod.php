<?php

namespace ChurchCRM\Utils;

class PaymentMethod
{
    /** The translated name of a stored payment method code; an unknown code prints as it is. */
    public static function label(string $code): string
    {
        return match ($code) {
            'CHECK' => gettext('Check'),
            'CASH' => gettext('Cash'),
            'CREDITCARD' => gettext('Credit Card'),
            'BANKDRAFT' => gettext('Bank Draft'),
            default => $code,
        };
    }
}
