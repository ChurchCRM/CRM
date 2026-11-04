<?php

namespace ChurchCRM\Portal;

use ChurchCRM\model\ChurchCRM\PersonSignin;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\Portal\Auth\SignInMethod;
use ChurchCRM\Service\MemberSignInListService;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use DateTimeInterface;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The numbers the Admin → Member Portal statistics tab shows, read from the
 * person sign-in rows (member sign-in design §11.2): `psi_LastActivity` for
 * "active now", `psi_LastSignIn` for the sign-in windows, and the member rule
 * for who may sign in. Every count is a `SELECT COUNT(*)`; only the ten most
 * recent sign-ins are read as rows.
 *
 * Datetime columns hold wall-clock time in the church's time zone, so every
 * cutoff is computed there.
 */
class PortalStatsService
{
    /** "Active now" window, in minutes. */
    public const ACTIVE_WINDOW_MINUTES = 15;

    public const RECENT_SIGN_IN_LIMIT = 10;

    /**
     * @return array{
     *     maySignIn: int,
     *     activeNow: int,
     *     signedIn24Hours: int,
     *     signedIn7Days: int,
     *     signedIn30Days: int,
     *     neverSignedIn: int,
     *     activeWindowMinutes: int,
     *     recentSignIns: list<array<string, mixed>>
     * }
     */
    public static function getStatistics(): array
    {
        $lists = new MemberSignInListService();

        return [
            'maySignIn' => $lists->countMaySignInNow(),
            'activeNow' => self::countActiveSince(self::cutoff('-' . self::ACTIVE_WINDOW_MINUTES . ' minutes')),
            'signedIn24Hours' => self::countSignedInSince(self::cutoff('-24 hours')),
            'signedIn7Days' => self::countSignedInSince(self::cutoff('-7 days')),
            'signedIn30Days' => self::countSignedInSince(self::cutoff('-30 days')),
            'neverSignedIn' => $lists->countNeverSignedIn(),
            'activeWindowMinutes' => self::ACTIVE_WINDOW_MINUTES,
            'recentSignIns' => self::getRecentSignIns(),
        ];
    }

    public static function countSignedInSince(DateTimeInterface $since): int
    {
        return PersonSigninQuery::create()
            ->filterByLastSignIn(['min' => $since->format('Y-m-d H:i:s')])
            ->count();
    }

    public static function countActiveSince(DateTimeInterface $since): int
    {
        return PersonSigninQuery::create()
            ->filterByLastActivity(['min' => $since->format('Y-m-d H:i:s')])
            ->count();
    }

    /**
     * The ten most recent portal sign-ins, newest first.
     *
     * @return list<array{personId: int, name: string, method: string|null, methodLabel: string|null, lastSignIn: string, lastActivity: string}>
     */
    public static function getRecentSignIns(): array
    {
        $states = PersonSigninQuery::create()
            ->filterByLastSignIn(null, Criteria::ISNOTNULL)
            ->joinWithPerson()
            ->orderByLastSignIn(Criteria::DESC)
            ->limit(self::RECENT_SIGN_IN_LIMIT)
            ->find();

        $rows = [];
        foreach ($states as $state) {
            /* @var PersonSignin $state */
            $method = SignInMethod::tryFrom((string) $state->getLastMethod());
            $rows[] = [
                'personId' => (int) $state->getPersonId(),
                'name' => (string) $state->getPerson()?->getFullName(),
                'method' => $method?->value,
                'methodLabel' => $method?->label(),
                'lastSignIn' => self::formatMoment($state->getLastSignIn()),
                'lastActivity' => self::formatMoment($state->getLastActivity()),
            ];
        }

        return $rows;
    }

    private static function formatMoment(mixed $moment): string
    {
        return $moment instanceof DateTimeInterface ? $moment->format('Y-m-d H:i:s') : '';
    }

    /**
     * "Now" minus a strtotime modifier, in the church's configured timezone.
     */
    private static function cutoff(string $modifier): DateTimeImmutable
    {
        return (new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone()))->modify($modifier);
    }
}
