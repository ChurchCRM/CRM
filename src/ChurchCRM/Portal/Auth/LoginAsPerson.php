<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\LoginAsPersonAction;
use ChurchCRM\model\ChurchCRM\LoginAsPersonSession;
use ChurchCRM\model\ChurchCRM\LoginAsPersonSessionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Portal\PortalExtension;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;
use DateTimeImmutable;
use LogicException;
use Throwable;

/**
 * Login as Person (member sign-in design §6.5): an administrator opens the Member
 * Portal as a person, through the shared step with method ASSISTED.
 *
 * The administrator's staff session is taken out of the PHP session and kept
 * here while it runs. No staff page or staff API can see it, so the only way
 * back to the staff side as themselves is Stop, which puts it back. It keeps its
 * own timeout (`iSessionTimeout`) from the last Login as Person request.
 *
 * Every session is a `login_as_person_session_lps` row naming both people, and
 * every write made during it a `login_as_person_action_lpa` row.
 */
final class LoginAsPerson
{
    public const STOP_PATH = '/portal/login-as-person/stop';

    private const SESSION_KEY = 'LoginAsPerson';
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public static function isEnabled(): bool
    {
        return SystemConfig::getBooleanValue('bAllowLoginAsPerson');
    }

    public static function allowsChanges(): bool
    {
        return SystemConfig::getBooleanValue('bLoginAsPersonCanWrite');
    }

    /** Why an administrator may not log in as this person, or null: member sign-in off, deceased, Disable sign-in or a staff account. */
    public static function refusalFor(Person $person): ?SignInReason
    {
        return PortalSignInRules::refusalReason($person, PersonSigninQuery::create()->findPk($person->getId()), SignInMethod::ASSISTED);
    }

    /** The person page's Actions item names the person, e.g. "Login as Pat". */
    public static function menuLabel(Person $person): string
    {
        return sprintf(gettext('Login as %s'), $person->getFirstName() ?: $person->getFullName());
    }

    /** For the grayed-out menu item and a refused start. */
    public static function refusalMessage(SignInReason $reason): string
    {
        return match ($reason) {
            SignInReason::MEMBER_SIGN_IN_OFF => gettext('Member sign-in is off.'),
            SignInReason::DECEASED => gettext('This person is deceased.'),
            SignInReason::SIGN_IN_DISABLED => gettext('Sign-in is disabled for this person.'),
            SignInReason::STAFF_ACCOUNT => gettext('This person has a staff account. Login as Person never opens a staff account.'),
            default => $reason->label(),
        };
    }

    public static function isActive(): bool
    {
        return self::current() !== null;
    }

    public static function historyId(): ?int
    {
        return self::current()['historyId'] ?? null;
    }

    /**
     * @throws PortalSignInRefusedException when the person may not be signed in as; the refusal is audited
     */
    public static function start(Person $person, User $admin): void
    {
        if (self::isActive()) {
            throw new LogicException('Login as Person is already running in this session');
        }
        $staffSession = AuthenticationManager::setAsideSessionProvider()
            ?? throw new LogicException('Login as Person needs a signed-in staff session');

        try {
            $principal = PortalSessionStarter::start((int) $person->getId(), SignInMethod::ASSISTED, $admin);
            $history = (new LoginAsPersonSession())
                ->setActorUserId($principal->actorUserId)
                ->setPersonId($principal->personId)
                ->setStarted(self::now());
            $history->save();
        } catch (Throwable $e) {
            if (PortalSessionStore::load()?->isAssisted()) {
                PortalSessionStore::clear();
            }
            AuthenticationManager::restoreSessionProvider($staffSession);

            throw $e;
        }

        $_SESSION[self::SESSION_KEY] = [
            'historyId' => (int) $history->getId(),
            'personId' => $principal->personId,
            'actorUserId' => $principal->actorUserId,
            'staffSession' => $staffSession,
        ];
        LoggerUtils::getAuthLogger()->info('Login as Person started', [
            'admin' => $principal->actorUserId,
            'person' => $principal->personId,
            'session' => (int) $history->getId(),
        ]);
    }

    /**
     * Ends the running session and puts the administrator's staff session back.
     *
     * @return int|null the person's id, or null when the administrator can no longer
     *                  be restored and the whole session has ended
     */
    public static function stop(): ?int
    {
        $record = self::current();
        if ($record === null) {
            return null;
        }
        SignInAudit::record(SignInAuditEvent::LOGIN_AS_STOPPED, $record['personId'], SignInMethod::ASSISTED, null, $record['actorUserId']);
        self::finish($record, LoginAsPersonEndReason::STOP, true);

        $admin = UserQuery::create()->findPk($record['actorUserId']);
        if (!$admin instanceof User || !$admin->isAdmin() || !AuthenticationManager::validateUserSessionIsActive(false)) {
            AuthenticationManager::endSession(true);

            return null;
        }

        return $record['personId'];
    }

    /**
     * `PortalSessionMiddleware` ended the ASSISTED principal. Idle time ends the
     * administrator's waiting staff session too; any other reason returns them to
     * it, and their next staff page says why.
     */
    public static function endedByPortal(SignInReason $reason): void
    {
        $record = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($record)) {
            return;
        }
        if ($reason === SignInReason::IDLE) {
            self::finish($record, LoginAsPersonEndReason::TIMEOUT, false);
            AuthenticationManager::endSession(true);

            return;
        }
        self::finish($record, $reason === SignInReason::MEMBER_SIGN_IN_OFF ? LoginAsPersonEndReason::MEMBER_SIGN_IN_OFF : LoginAsPersonEndReason::ENDED, true);
        $_SESSION['sGlobalMessage'] = sprintf(gettext('Login as Person ended: %s'), self::refusalMessage($reason));
        $_SESSION['sGlobalMessageClass'] = 'warning';
    }

    /** The PHP session is being destroyed (sign-out, or a staff session that ended). */
    public static function endWithSession(): void
    {
        $record = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_array($record)) {
            self::finish($record, LoginAsPersonEndReason::SIGN_OUT, false);
        }
    }

    /** A username and password sign-in starts a new identity in this browser. */
    public static function endForNewSignIn(): void
    {
        $record = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($record)) {
            return;
        }
        SignInAudit::record(SignInAuditEvent::SESSION_ENDED, $record['personId'], SignInMethod::ASSISTED, SignInReason::REPLACED, $record['actorUserId']);
        self::finish($record, LoginAsPersonEndReason::SIGN_OUT, false);
    }

    /** False once the waiting staff session has timed out; otherwise it counts this request as activity. */
    public static function keepStaffSessionAlive(): bool
    {
        $staffSession = ($_SESSION[self::SESSION_KEY] ?? [])['staffSession'] ?? null;

        return $staffSession instanceof LocalAuthentication && $staffSession->validateUserSessionIsActive(true)->isAuthenticated;
    }

    public static function staffPagesClosedMessage(): string
    {
        return gettext('Staff pages stay closed during Login as Person. Press Stop in the banner to go back.');
    }

    /** For legacy pages, which redirect and exit. */
    public static function refuseStaffPage(): never
    {
        PortalExtension::addFlash('warning', self::staffPagesClosedMessage());
        RedirectUtils::redirect('portal/');

        exit;
    }

    public static function isWriteMethod(string $method): bool
    {
        return !in_array(strtoupper($method), self::READ_METHODS, true);
    }

    public static function isStopPath(string $path): bool
    {
        return self::appPath($path) === self::STOP_PATH;
    }

    /** `$path` should carry no query string; a failed write is logged, never thrown. */
    public static function recordAction(int $historyId, string $method, string $path, ?int $status): void
    {
        try {
            (new LoginAsPersonAction())
                ->setSessionId($historyId)
                ->setTime(self::now())
                ->setMethod(substr(strtoupper($method), 0, 10))
                ->setPath(mb_substr(self::appPath($path), 0, 255))
                ->setStatus($status !== null && $status > 0 ? $status : null)
                ->save();
        } catch (Throwable $e) {
            LoggerUtils::getAuthLogger()->error('Could not record a Login as Person action', [
                'session' => $historyId,
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * For the portal layout's banner.
     *
     * @return array{personName: string, actorName: string, allowsChanges: bool, stopUrl: string}|null
     */
    public static function banner(): ?array
    {
        $record = self::current();
        if ($record === null) {
            return null;
        }

        return [
            'personName' => (string) PersonQuery::create()->findPk($record['personId'])?->getFullName(),
            'actorName' => (string) PersonQuery::create()->findPk($record['actorUserId'])?->getFullName(),
            'allowsChanges' => self::allowsChanges(),
            'stopUrl' => SystemURLs::getRootPath() . self::STOP_PATH,
        ];
    }

    /**
     * The running session's record. A record whose ASSISTED principal has gone
     * (a portal page cleared it) is closed here, and the administrator gets their
     * staff session back.
     *
     * @return array{historyId: int, personId: int, actorUserId: int, staffSession: LocalAuthentication|null}|null
     */
    private static function current(): ?array
    {
        $record = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($record)) {
            return null;
        }
        $principal = PortalSessionStore::load();
        if (
            $principal instanceof PortalPrincipal
            && $principal->isAssisted()
            && $principal->personId === ($record['personId'] ?? null)
            && $principal->actorUserId === ($record['actorUserId'] ?? null)
        ) {
            return $record;
        }
        self::finish($record, LoginAsPersonEndReason::ENDED, true);

        return null;
    }

    private static function finish(array $record, LoginAsPersonEndReason $reason, bool $restoreStaffSession): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        if (PortalSessionStore::load()?->isAssisted()) {
            PortalSessionStore::clear();
        }

        $staffSession = $record['staffSession'] ?? null;
        if ($restoreStaffSession && $staffSession instanceof LocalAuthentication) {
            AuthenticationManager::restoreSessionProvider($staffSession);
        }

        try {
            $history = LoginAsPersonSessionQuery::create()->findPk((int) ($record['historyId'] ?? 0));
            if ($history instanceof LoginAsPersonSession && $history->getEnded() === null) {
                $history->setEnded(self::now())->setEndReason($reason->value)->save();
            }
        } catch (Throwable $e) {
            LoggerUtils::getAuthLogger()->error('Could not close a Login as Person session', [
                'session' => $record['historyId'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        LoggerUtils::getAuthLogger()->info('Login as Person ended', [
            'admin' => $record['actorUserId'] ?? null,
            'person' => $record['personId'] ?? null,
            'session' => $record['historyId'] ?? null,
            'reason' => $reason->value,
        ]);
    }

    /** `$path` without the install's root path, so a subdirectory install records `/api/...` too. */
    private static function appPath(string $path): string
    {
        $rootPath = SystemURLs::getRootPath();
        if ($rootPath !== '' && str_starts_with($path, $rootPath . '/')) {
            return substr($path, strlen($rootPath));
        }

        return $path;
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
    }
}
