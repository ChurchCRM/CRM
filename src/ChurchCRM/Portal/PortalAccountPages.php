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
 * A member-facing page never wears the admin shell, for any role: the portal
 * owns both pages at `/portal/profile/password` and `/portal/profile/two-factor`
 * (see `src/portal/routes/profile.php`), and they render the same way for a
 * member, for staff and for an administrator. They are also where
 * `LocalAuthentication` sends a self-service login for a forced password change
 * or a forced 2FA enrollment; staff are sent to `/account/password` and
 * `/account/2fa`.
 *
 * The password change itself has one implementation —
 * `User::userChangePassword()` — and both the portal and `/account` reach it through
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
     * The handler behind both routes, so `OldPassword` / `NewPassword1` /
     * `NewPassword2` are named once. `skin/js/PasswordChange.js` compares the
     * two new boxes in the browser too, but a form that skips it must not save.
     *
     * @param array<string, mixed>|object|null $body the parsed request body
     *
     * @throws PasswordChangeException when the new password is blank, the two
     *                                 new boxes differ, the old password is
     *                                 wrong or the new one is refused
     */
    public static function applyPasswordChange(User $user, array|object|null $body): void
    {
        $fields = is_array($body) ? $body : [];
        $newPassword = (string) ($fields['NewPassword1'] ?? '');

        if (trim($newPassword) === '') {
            throw new PasswordChangeException('New', gettext('Enter a new password'));
        }

        if ($newPassword !== (string) ($fields['NewPassword2'] ?? '')) {
            throw new PasswordChangeException('New', gettext('You must enter the same password in both boxes'));
        }

        $user->userChangePassword((string) ($fields['OldPassword'] ?? ''), $newPassword);
    }

    /**
     * The password form, in the portal layout.
     *
     * `$formAction` is the URL the form posts back to. It is never taken from
     * the request, so there is no redirect for an attacker to steer.
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
