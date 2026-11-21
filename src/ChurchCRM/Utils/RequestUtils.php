<?php

namespace ChurchCRM\Utils;

class RequestUtils
{
    /**
     * X-Forwarded-Proto is honoured only when the direct peer is listed in the
     * CHURCHCRM_TRUSTED_PROXIES environment variable (comma-separated IPs or CIDRs).
     */
    public static function isHttps(?array $server = null, ?string $trustedProxies = null): bool
    {
        $server ??= $_SERVER;
        if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
            return true;
        }

        return ($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            && self::isTrustedProxy($server['REMOTE_ADDR'] ?? '', $trustedProxies ?? (string) getenv('CHURCHCRM_TRUSTED_PROXIES'));
    }

    /**
     * The address of the client behind this request. When the direct peer is
     * listed in CHURCHCRM_TRUSTED_PROXIES, X-Forwarded-For is read from the
     * right, past the listed proxies, to the first address that is not one.
     * Every listed address is believed, so the list must name proxies only: a
     * client inside a listed range could choose the address it is counted by.
     * Otherwise, or when that walk meets something that is not an IP address
     * or finds no client, the direct peer.
     */
    public static function clientAddress(?array $server = null, ?string $trustedProxies = null): string
    {
        $server ??= $_SERVER;
        $trustedProxies ??= (string) getenv('CHURCHCRM_TRUSTED_PROXIES');
        $peer = (string) ($server['REMOTE_ADDR'] ?? '');
        if (!self::isTrustedProxy($peer, $trustedProxies)) {
            return $peer;
        }

        $forwarded = array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '')));
        foreach (array_reverse($forwarded) as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                return $peer;
            }
            if (!self::isTrustedProxy($address, $trustedProxies)) {
                return $address;
            }
        }

        return $peer;
    }

    /**
     * An IPv4-mapped address (::ffff:a.b.c.d), as a dual-stack server reports
     * an IPv4 peer, matches both IPv4 entries and IPv6 entries.
     */
    public static function isTrustedProxy(string $ip, string $trustedProxies): bool
    {
        $address = @inet_pton($ip);
        if ($address === false) {
            return false;
        }
        $forms = [strlen($address) => $address];
        if (str_starts_with($address, str_repeat("\0", 10) . "\xff\xff")) {
            $forms[4] = substr($address, 12);
        }
        foreach (array_filter(array_map('trim', explode(',', $trustedProxies))) as $entry) {
            [$net, $bits] = array_pad(explode('/', $entry, 2), 2, null);
            $netPacked = @inet_pton($net);
            if ($netPacked === false || !isset($forms[strlen($netPacked)])) {
                continue;
            }
            $packed = $forms[strlen($netPacked)];
            $width = strlen($packed) * 8;
            if ($bits !== null && (!ctype_digit($bits) || (int) $bits > $width)) {
                continue;
            }
            $bits = $bits === null ? $width : (int) $bits;
            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;
            if (substr($packed, 0, $bytes) !== substr($netPacked, 0, $bytes)) {
                continue;
            }
            $mask = $rest === 0 ? 0 : (0xFF << (8 - $rest)) & 0xFF;
            if ($rest === 0 || (ord($packed[$bytes]) & $mask) === (ord($netPacked[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
