<?php

namespace ChurchCRM\Portal;

use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use Psr\Http\Message\ResponseInterface;

/**
 * The Member Portal's account pages — "Change your password" and "Two-factor
 * authentication" — and the one place they are turned into a response.
 *
 * Why this class exists: the portal's product principle is that a member-facing
 * page never wears the admin shell, and the product owner extended that to
 * *every* role. Until now the two account pages lived only at
 * `/v2/user/current/changepassword` and `/v2/user/current/manage2fa`, which
 * render the portal layout for an Edit-Self-only session and the admin shell
 * for anybody else — so an administrator who opened the portal and clicked
 * "Change password" was dropped back into the admin console. The portal now
 * owns both pages at `/portal/profile/password` and `/portal/profile/two-factor`
 * (see `src/portal/routes/profile.php`), and they render the same way for a
 * member, for staff, for an administrator and during a masquerade.
 *
 * The old `/v2/user/current/*` URLs keep their behaviour exactly: they are what
 * `LocalAuthentication` hands back as `nextStepURL` for a forced password change
 * or a forced 2FA enrollment, and what `AuthMiddleware::isLimitedAccessAllowedPath()`
 * exempts by name, so a member cannot be locked out. They are *not* redirected
 * to the portal URLs: the forced flows break out of their redirect loop by
 * matching `/v2/user/current/changepassword` against `REQUEST_URI`, so a
 * redirect there would bounce the browser between the two paths forever. What
 * they do instead is render through this class, so there is one portal
 * rendering path and not two.
 *
 * The password change itself has one implementation either way —
 * `User::userChangePassword()` — and both routes reach it through
 * `applyPasswordChange()` below, so the form's field names live in one place.
 */
class PortalAccountPages
{
    /** Paths relative to the install root; use the url*() helpers to build links. */
    public const PASSWORD_PATH = '/portal/profile/password';
    public const TWO_FACTOR_PATH = '/portal/profile/two-factor';

    public const PASSWORD_TEMPLATE = 'profile/password.html.twig';
    public const PASSWORD_CHANGED_TEMPLATE = 'profile/password-changed.html.twig';
    public const TWO_FACTOR_TEMPLATE = 'profile/two-factor.html.twig';

    /** The portal's own "change your password" page. */
    public static function getPasswordUrl(): string
    {
        return SystemURLs::getRootPath() . self::PASSWORD_PATH;
    }

    /** The portal's own two-factor page. */
    public static function getTwoFactorUrl(): string
    {
        return SystemURLs::getRootPath() . self::TWO_FACTOR_PATH;
    }

    /**
     * Apply a password change from a submitted form body.
     *
     * The handler behind both routes, so `OldPassword` / `NewPassword1` are
     * named once. `NewPassword2` is the confirmation box that
     * `skin/js/PasswordChange.js` compares in the browser; the server has never
     * read it and does not start here.
     *
     * @param array<string, mixed>|object|null $body the parsed request body
     *
     * @throws PasswordChangeException when the old password is wrong or the new
     *                                 one is refused
     */
    public static function applyPasswordChange(User $user, array|object|null $body): void
    {
        $fields = is_array($body) ? $body : [];

        $user->userChangePassword(
            (string) ($fields['OldPassword'] ?? ''),
            (string) ($fields['NewPassword1'] ?? '')
        );
    }

    /**
     * The password form, in the portal layout.
     *
     * `$formAction` is the URL the form posts back to, so the page returns to
     * wherever it was opened from: the portal route posts to the portal, and
     * the forced-change page at `/v2/user/current/changepassword` posts to
     * itself. It is never taken from the request — a caller passes one of the
     * two known routes — so there is no redirect for an attacker to steer.
     */
    public static function renderPasswordForm(
        ResponseInterface $response,
        string $formAction,
        string $oldPasswordError = '',
        string $newPasswordError = ''
    ): ResponseInterface {
        return self::render($response, self::PASSWORD_TEMPLATE, gettext('Change your password'), [
            'formAction' => $formAction,
            'minPasswordLength' => SystemConfig::getIntValue('iMinPasswordLength'),
            'oldPasswordError' => $oldPasswordError,
            'newPasswordError' => $newPasswordError,
        ]);
    }

    /** The confirmation shown after a successful change, in the portal layout. */
    public static function renderPasswordChanged(ResponseInterface $response): ResponseInterface
    {
        return self::render($response, self::PASSWORD_CHANGED_TEMPLATE, gettext('Change your password'));
    }

    /**
     * The two-factor page, in the portal layout. The enrollment UI is the
     * shared `two-factor-enrollment` bundle the template mounts, and it talks
     * to `/api/user/current/*`, which every role may call.
     */
    public static function renderTwoFactor(ResponseInterface $response): ResponseInterface
    {
        return self::render($response, self::TWO_FACTOR_TEMPLATE, gettext('Two-Factor Authentication'));
    }

    /**
     * @param array<string, mixed> $model
     */
    private static function render(
        ResponseInterface $response,
        string $template,
        string $pageTitle,
        array $model = []
    ): ResponseInterface {
        // PortalAccessMiddleware already did this for the portal's own routes;
        // the /v2 routes have no portal middleware in front of them. Both
        // operations are idempotent, so calling it here covers either caller.
        PortalTwig::preparePage();

        return PortalTwig::render(
            $response,
            $template,
            array_merge(['pageTitle' => $pageTitle], $model),
            PortalNav::PROFILE
        );
    }
}
