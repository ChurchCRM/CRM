<?php

namespace ChurchCRM\Utils;

final class EmailAddress
{
    public static function isValid(string $email): bool
    {
        $email = trim($email);
        $at = strrpos($email, '@');
        if ($at === false || preg_match('/[^\x00-\x7F]/', substr($email, 0, $at))) {
            return false;
        }

        $domain = substr($email, $at + 1);
        if (preg_match('/[^\x00-\x7F]/', $domain)) {
            if (!function_exists('idn_to_ascii')) {
                return false;
            }
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($domain === false) {
                return false;
            }
        }

        return filter_var(substr($email, 0, $at + 1) . $domain, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validationMessage(): string
    {
        return gettext('Enter a valid email address using only ASCII characters before @.');
    }
}
