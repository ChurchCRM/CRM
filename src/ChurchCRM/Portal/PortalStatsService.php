<?php

namespace ChurchCRM\Portal;

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use DateTimeInterface;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The numbers the Admin → Member Portal statistics tab shows (design §4).
 *
 * Every count is a `SELECT COUNT(*)` with a `WHERE` clause; no user row is
 * hydrated to produce a number. Only the ten most recent sign-ins are read as
 * objects, because that list needs names.
 *
 * "Self-service account" is `User::isEditSelfExclusive()` expressed in SQL:
 * `usr_EditSelf = 1 AND usr_Admin = 0`. That is the whole definition — an
 * account like the seeded `lena.black` carries `usr_Notes = 1` and is still
 * confined to the portal, because every module permission short-circuits to
 * false for an EditSelf-exclusive login (see the "EditSelf-exclusive
 * invariant" note in User.php). Adding "and no other flag is set" to the query
 * here would silently drop exactly those accounts from the statistics.
 *
 * Datetime columns hold wall-clock time in the church's configured timezone
 * (`sTimeZone`) — that is how `usr_LastLogin` is written by
 * `LocalAuthentication` — so every cutoff below is computed in that timezone.
 */
class PortalStatsService
{
    /** "Active now" window, in minutes (design §4). */
    public const ACTIVE_WINDOW_MINUTES = 15;

    /** How long a stamped `usr_LastPortalActivity` is left alone, in minutes. */
    public const ACTIVITY_STAMP_INTERVAL_MINUTES = 5;

    /** How many recent sign-ins the page lists. */
    public const RECENT_SIGN_IN_LIMIT = 10;

    /**
     * Everything the statistics tab renders.
     *
     * @return array{
     *     totalAccounts: int,
     *     activeNow: int,
     *     signedIn24Hours: int,
     *     signedIn7Days: int,
     *     signedIn30Days: int,
     *     neverSignedIn: int,
     *     activeWindowMinutes: int,
     *     recentSignIns: array<int, array{personId: int, name: string, userName: string, lastLogin: string, lastPortalActivity: string}>
     * }
     */
    public static function getStatistics(): array
    {
        return [
            'totalAccounts' => self::countSelfServiceAccounts(),
            'activeNow' => self::countActiveSince(self::cutoff('-' . self::ACTIVE_WINDOW_MINUTES . ' minutes')),
            'signedIn24Hours' => self::countSignedInSince(self::cutoff('-24 hours')),
            'signedIn7Days' => self::countSignedInSince(self::cutoff('-7 days')),
            'signedIn30Days' => self::countSignedInSince(self::cutoff('-30 days')),
            'neverSignedIn' => self::countNeverSignedIn(),
            'activeWindowMinutes' => self::ACTIVE_WINDOW_MINUTES,
            'recentSignIns' => self::getRecentSignIns(),
        ];
    }

    /**
     * Every self-service account, signed in or not.
     */
    public static function countSelfServiceAccounts(): int
    {
        return self::selfServiceQuery()->count();
    }

    /**
     * Self-service accounts whose last real sign-in is at or after `$since`.
     * A masquerade does not stamp `usr_LastLogin` (#9843), so these are real
     * member sign-ins only.
     */
    public static function countSignedInSince(DateTimeInterface $since): int
    {
        return self::signedInQuery()
            ->filterByLastLogin(['min' => $since->format('Y-m-d H:i:s')])
            ->count();
    }

    /**
     * Self-service accounts that opened a portal page at or after `$since`.
     */
    public static function countActiveSince(DateTimeInterface $since): int
    {
        return self::selfServiceQuery()
            ->filterByLastPortalActivity(['min' => $since->format('Y-m-d H:i:s')])
            ->count();
    }

    /**
     * Self-service accounts that have never signed in. `usr_LoginCount` is the
     * honest test: `usr_LastLogin` carries a non-null placeholder date for an
     * account that has never been used.
     */
    public static function countNeverSignedIn(): int
    {
        return self::selfServiceQuery()->filterByLoginCount(0)->count();
    }

    /**
     * The ten most recent member sign-ins, newest first.
     *
     * `lastPortalActivity` is the throttled stamp this service writes; it is
     * what "Last seen in the portal" on the admin page reads, and an empty
     * string means the member has signed in but not opened a portal page since
     * the column was added.
     *
     * @return array<int, array{personId: int, name: string, userName: string, lastLogin: string, lastPortalActivity: string}>
     */
    public static function getRecentSignIns(): array
    {
        $users = self::signedInQuery()
            ->orderByLastLogin(Criteria::DESC)
            ->limit(self::RECENT_SIGN_IN_LIMIT)
            ->find();

        $rows = [];
        foreach ($users as $user) {
            /* @var User $user */
            $rows[] = [
                'personId' => (int) $user->getPersonId(),
                'name' => (string) $user->getFullName(),
                'userName' => (string) $user->getUserName(),
                'lastLogin' => self::formatMoment($user->getLastLogin()),
                'lastPortalActivity' => self::formatMoment($user->getLastPortalActivity()),
            ];
        }

        return $rows;
    }

    /**
     * A datetime column as the page and the API show it, or '' when unset.
     */
    private static function formatMoment(mixed $moment): string
    {
        return $moment instanceof DateTimeInterface ? $moment->format('Y-m-d H:i:s') : '';
    }

    /**
     * Stamp "this account was just in the portal", at most once every
     * ACTIVITY_STAMP_INTERVAL_MINUTES. The throttle compares the *stored*
     * value, not a session flag, so it still holds when a member's session is
     * recreated — which is the whole point of the column (design §4).
     *
     * @return bool whether the column was written
     */
    public static function recordPortalActivity(User $user): bool
    {
        $now = DateTimeUtils::getToday();
        $last = $user->getLastPortalActivity();

        if ($last instanceof DateTimeInterface) {
            $secondsSince = $now->getTimestamp() - $last->getTimestamp();
            if ($secondsSince >= 0 && $secondsSince < self::ACTIVITY_STAMP_INTERVAL_MINUTES * 60) {
                return false;
            }
        }

        $user->setLastPortalActivity($now->format('Y-m-d H:i:s'));
        $user->save();

        return true;
    }

    /**
     * `usr_EditSelf = 1 AND usr_Admin = 0` — the self-service persona, and the
     * only population the portal statistics describe.
     */
    private static function selfServiceQuery(): UserQuery
    {
        return UserQuery::create()
            ->filterByEditSelf(true)
            ->filterByAdmin(false);
    }

    /**
     * Self-service accounts that have signed in at least once.
     */
    private static function signedInQuery(): UserQuery
    {
        return self::selfServiceQuery()->filterByLoginCount(0, Criteria::GREATER_THAN);
    }

    /**
     * "Now" minus a strtotime modifier, in the church's configured timezone.
     */
    private static function cutoff(string $modifier): DateTimeImmutable
    {
        return (new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone()))->modify($modifier);
    }
}
