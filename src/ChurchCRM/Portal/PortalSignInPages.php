<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\RememberedDeviceCookie;
use ChurchCRM\Portal\Auth\RememberedDeviceToken;
use ChurchCRM\Service\PasskeyService;
use ChurchCRM\Service\RememberedDeviceService;
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
     * After an email or passkey sign-in (design §6.2): a device cookie only
     * when the member ticked "This is my own device" after an email sign-in,
     * and then the one-time passkey offer (§6.3). Either way, a device cookie
     * this browser already held is signed out, so nobody else's device
     * survives a new sign-in here.
     */
    public static function rememberDevice(ServerRequestInterface $request, ResponseInterface $response, bool $chosen): ResponseInterface
    {
        $devices = new RememberedDeviceService();
        $previous = RememberedDeviceCookie::read($request);
        if ($previous !== '') {
            $devices->forgetToken($previous);
        }

        $principal = PortalSessionStore::load();
        $token = $chosen && $principal instanceof PortalPrincipal && $principal->method->isEmail()
            ? $devices->remember($principal->personId, $principal->method, $request->getHeaderLine('User-Agent'))
            : null;

        if ($token instanceof RememberedDeviceToken) {
            PasskeyService::offerAfterSignIn($request);

            return RememberedDeviceCookie::with($response, $token);
        }

        return $previous !== '' ? RememberedDeviceCookie::without($response) : $response;
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
