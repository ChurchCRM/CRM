<?php

// Composer loads this on every request: without the gettext extension, text stays in English instead of fataling.
if (!function_exists('gettext')) {
    function gettext(string $message): string
    {
        return $message;
    }
}
if (!function_exists('_')) {
    function _(string $message): string
    {
        return $message;
    }
}
if (!function_exists('ngettext')) {
    function ngettext(string $singular, string $plural, int $count): string
    {
        return $count === 1 ? $singular : $plural;
    }
}
if (!function_exists('dgettext')) {
    function dgettext(string $domain, string $message): string
    {
        return $message;
    }
}
if (!function_exists('bindtextdomain')) {
    function bindtextdomain(string $domain, ?string $directory = null): string|false
    {
        return $directory ?? false;
    }
}
if (!function_exists('bind_textdomain_codeset')) {
    function bind_textdomain_codeset(string $domain, ?string $codeset = null): string|false
    {
        return $codeset ?? false;
    }
}
if (!function_exists('textdomain')) {
    function textdomain(?string $domain = null): string
    {
        return $domain ?? 'messages';
    }
}
