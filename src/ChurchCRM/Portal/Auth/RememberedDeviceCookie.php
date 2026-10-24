<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RequestUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The cookie that keeps a device signed in (member sign-in design §6.2, §14):
 * long-lived, HttpOnly, SameSite=Lax, Secure on HTTPS and sent only to
 * `/portal`. It carries the random token; the database holds only its hash.
 */
final class RememberedDeviceCookie
{
    public const NAME = 'CRM-portal-device';

    public static function read(ServerRequestInterface $request): string
    {
        $value = $request->getCookieParams()[self::NAME] ?? '';

        return is_string($value) && preg_match('/^[0-9a-f]{64}$/', $value) ? $value : '';
    }

    public static function isPresent(ServerRequestInterface $request): bool
    {
        return self::read($request) !== '';
    }

    /** A token with no value leaves the cookie alone: a parallel response already set the newer token. */
    public static function with(ResponseInterface $response, RememberedDeviceToken $token): ResponseInterface
    {
        if ($token->value === null) {
            return $response;
        }

        return $response->withAddedHeader('Set-Cookie', self::header($token->value, max(1, $token->expiresAt->getTimestamp() - time())));
    }

    /** The cookie's path matches `with()` exactly, or the browser would keep the old one. */
    public static function without(ResponseInterface $response): ResponseInterface
    {
        return $response->withAddedHeader('Set-Cookie', self::header('', 0));
    }

    private static function header(string $value, int $maxAge): string
    {
        $isHttps = RequestUtils::isHttps();

        return sprintf(
            '%s=%s; Path=%s; Max-Age=%d; HttpOnly; SameSite=Lax%s',
            self::NAME,
            $value,
            SystemURLs::getRootPath() . '/portal',
            $maxAge,
            $isHttps ? '; Secure' : '',
        );
    }
}
