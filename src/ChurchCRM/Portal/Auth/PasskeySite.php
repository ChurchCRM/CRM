<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RequestUtils;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Where passkeys can work (member sign-in design §6.3, §11.1, §14): the
 * relying-party id is the host of the site address in Config.php, which must
 * be a host name served over HTTPS (or `localhost`, which browsers treat as
 * secure). A page reached at any other host can't offer them, because the
 * browser would refuse a passkey for an id that isn't its own host.
 */
final class PasskeySite
{
    public static function isTurnedOn(): bool
    {
        return SystemConfig::getBooleanValue('bPortalPasskeys');
    }

    public static function rpId(): string
    {
        return strtolower((string) (parse_url((string) SystemURLs::getURL(), PHP_URL_HOST) ?? ''));
    }

    /** Why the site address can't carry passkeys, for the Sign-in tab, or null. */
    public static function addressProblem(): ?string
    {
        $parts = parse_url((string) SystemURLs::getURL());
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return gettext('Passkeys need the site address to be set in Config.php.');
        }
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return gettext('Passkeys need a host name, not an IP address, in the site address.');
        }
        if (($parts['scheme'] ?? '') !== 'https' && $host !== 'localhost') {
            return gettext('Passkeys need the site to be served over HTTPS. Members are not offered passkeys until it is.');
        }

        return null;
    }

    /** Why this request can't use a passkey, in words for the member, or null. */
    public static function requestProblem(ServerRequestInterface $request): ?string
    {
        if (self::addressProblem() !== null) {
            return gettext('Passkeys cannot be used on this site yet. Please contact the church office.');
        }
        $rpId = self::rpId();
        if (strtolower($request->getUri()->getHost()) !== $rpId) {
            return sprintf(gettext('Passkeys work only at %s.'), $rpId);
        }
        if ($rpId !== 'localhost' && !self::isHttps($request)) {
            return gettext('Passkeys need a secure (HTTPS) connection to this site.');
        }

        return null;
    }

    public static function isAvailable(ServerRequestInterface $request): bool
    {
        return self::isTurnedOn() && self::requestProblem($request) === null;
    }

    /**
     * The origin the browser reports must be this site's own host, exactly,
     * over HTTPS (HTTP only for `localhost`). Any port is accepted: the port
     * is not part of a relying-party id, and a proxy may hide the real one.
     */
    public static function acceptsOrigin(string $origin): bool
    {
        $parts = parse_url($origin);
        if (!is_array($parts) || isset($parts['path']) || isset($parts['user']) || isset($parts['query'])) {
            return false;
        }
        $rpId = self::rpId();
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = (string) ($parts['scheme'] ?? '');

        return $rpId !== '' && $host === $rpId && ($scheme === 'https' || ($scheme === 'http' && $rpId === 'localhost'));
    }

    private static function isHttps(ServerRequestInterface $request): bool
    {
        return $request->getUri()->getScheme() === 'https' || RequestUtils::isHttps($request->getServerParams());
    }
}
