<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RequestUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Plumbing shared by the `/portal/login` pages: the cookie that ties an email
 * request to the browser that made it (design §6.1 step 5), and where a
 * member lands after signing in.
 */
final class PortalSignInPages
{
    public const BROWSER_COOKIE = 'CRM-portal-signin';

    public static function staffSignInUrl(): string
    {
        return AuthenticationManager::getSessionBeginURL();
    }

    public static function loginUrl(string $path = ''): string
    {
        return AuthenticationManager::getPortalLoginURL() . $path;
    }

    /** The "This is my own device" box (§6.2) is offered unless the church set its days to 0 (§11.1). */
    public static function offersRememberDevice(): bool
    {
        return SystemConfig::getIntValue('iRememberDeviceDays') > 0;
    }

    /**
     * The hook for remembered devices (row 7, design §6.2): when the member
     * ticked "This is my own device", the device cookie is added here. Until
     * then nothing is kept on the device, ticked or not.
     */
    public static function rememberDevice(ResponseInterface $response, bool $chosen): ResponseInterface
    {
        return $response;
    }

    public static function browserSecret(ServerRequestInterface $request): string
    {
        $value = $request->getCookieParams()[self::BROWSER_COOKIE] ?? '';

        return is_string($value) && preg_match('/^[0-9a-f]{64}$/', $value) ? $value : '';
    }

    public static function withBrowserCookie(ResponseInterface $response, string $secret): ResponseInterface
    {
        $lifetime = max(1, SystemConfig::getIntValue('iSignInLinkMinutes')) * 60;

        return $response->withAddedHeader('Set-Cookie', self::cookie($secret, $lifetime));
    }

    public static function withoutBrowserCookie(ResponseInterface $response): ResponseInterface
    {
        return $response->withAddedHeader('Set-Cookie', self::cookie('', 0));
    }

    /**
     * The portal page a signed-out visitor first asked for, or the portal home.
     * Only portal pages qualify: an email sign-in never opens the staff side.
     */
    public static function landingUrl(): string
    {
        $location = $_SESSION['location'] ?? '';
        unset($_SESSION['location']);
        $location = is_string($location) ? $location : '';
        $path = (string) parse_url($location, PHP_URL_PATH);
        $isPortalPage = ($path === '/portal' || str_starts_with($path, '/portal/')) && !str_starts_with($path, '/portal/login');

        return SystemURLs::getRootPath() . ($isPortalPage ? $location : '/portal/');
    }

    private static function cookie(string $value, int $maxAge): string
    {
        $isHttps = RequestUtils::isHttps();

        return sprintf(
            '%s=%s; Path=%s; Max-Age=%d; HttpOnly; SameSite=Lax%s',
            self::BROWSER_COOKIE,
            $value,
            self::loginUrl(),
            $maxAge,
            $isHttps ? '; Secure' : '',
        );
    }
}
