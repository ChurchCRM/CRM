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

    public static function isTrustedProxy(string $ip, string $trustedProxies): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        foreach (array_filter(array_map('trim', explode(',', $trustedProxies))) as $entry) {
            [$net, $bits] = array_pad(explode('/', $entry, 2), 2, null);
            $netPacked = @inet_pton($net);
            if ($netPacked === false || strlen($netPacked) !== strlen($packed)) {
                continue;
            }
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
