<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use DateTimeImmutable;

/**
 * A member's calendar subscription: which of the shared calendars they want in
 * their own calendar app, and the address that feeds them (design §5.3,
 * "Subscribing").
 *
 * The shape of the thing
 * ----------------------
 * Two nullable columns on `user_usr` hold it:
 *
 *  - `usr_PortalCalendarToken` — 32 random bytes as hex, minted the first time
 *    a member saves a selection and rotated by "Reset link". NULL means this
 *    account has never asked for a feed, and no URL answers for it.
 *  - `usr_PortalCalendarSelection` — a JSON array of the ids
 *    `PortalCalendarService::listChoices()` uses, in the `"<type>:<id>"` form
 *    (`"calendar:3"`, `"system:0"`).
 *
 * Security
 * --------
 * The token is a **bearer secret**. Anyone holding it can read every event on
 * the calendars the member selected, with no login — which is the point: a
 * calendar app cannot sign in. Three rules keep that bounded, and all three are
 * enforced here rather than at the edges:
 *
 *  1. **A feed can only ever narrow.** What it serves is the member's selection
 *     *intersected with the calendars the administrator currently shares*, so
 *     un-sharing a calendar in Admin → Member Portal empties it out of every
 *     member's feed on the next fetch, with nobody having to re-save anything.
 *  2. **A token names a feed, not a person.** Nothing but calendar events is
 *     reachable through it — no profile, no family, no name, not even whether
 *     the account still exists (`findByToken()` answers null for an unknown
 *     token and for an account that has no feed alike).
 *  3. **It is never logged.** No method here writes the token to a log, and
 *     neither does the route: a feed URL in an application log would be the
 *     credential itself, sitting in a file the church's whole staff can read.
 */
class PortalCalendarSubscription
{
    /** 32 bytes of entropy, written as 64 hex characters. */
    private const TOKEN_BYTES = 32;

    /** The feed starts three months back, so a member's app keeps recent history. */
    private const WINDOW_MONTHS_BACK = 3;

    /** …and runs eighteen months ahead, which covers next year's planning. */
    private const WINDOW_MONTHS_AHEAD = 18;

    /**
     * The calendars this member may tick, each flagged with whether they have.
     *
     * Exactly the calendars the administrator shares with members: a choice
     * that is not on this list cannot be saved, and one that leaves it stops
     * being served even though the stored selection still names it.
     *
     * @return array<int, array{id: string, name: string, color: string, selected: bool}>
     */
    public static function choices(User $user): array
    {
        $selected = self::selectionKeys($user);
        $nothingSaved = $selected === [];

        $choices = [];
        foreach (PortalCalendarService::listChoices() as $choice) {
            if (!$choice['visible']) {
                continue;
            }

            $id = self::id($choice['type'], $choice['id']);
            $choices[] = [
                'id' => $id,
                'name' => $choice['name'],
                'color' => $choice['colors']['background'],
                // Before a first save every shared calendar reads as ticked,
                // which is what the modal shows and what "subscribe to
                // everything" means to a member who just opened it.
                'selected' => $nothingSaved || isset($selected[$id]),
            ];
        }

        return $choices;
    }

    /**
     * The name a calendar app shows for the feed: the church's own name, so a
     * member with three churches' calendars can tell them apart.
     */
    public static function title(): string
    {
        $churchName = trim(ChurchMetaData::getChurchName());

        return $churchName === ''
            ? gettext('Church Calendar')
            : sprintf(
                /* Translators: %s is the church's name, e.g. "Main St. Cathedral Calendar". */
                gettext('%s Calendar'),
                $churchName
            );
    }

    /**
     * Store a member's selection, minting their token if this is the first time.
     *
     * @param array<int, mixed> $ids the ids the member ticked
     *
     * @throws PortalSelfServiceException when the selection is empty or names a calendar the portal does not share (400)
     */
    public static function save(User $user, array $ids): void
    {
        $allowed = [];
        foreach (self::choices($user) as $choice) {
            $allowed[$choice['id']] = true;
        }

        $accepted = [];
        foreach ($ids as $id) {
            if (!is_string($id)) {
                throw new PortalSelfServiceException(gettext('Please choose calendars from the list.'));
            }

            $id = trim($id);
            if (!isset($allowed[$id])) {
                // Deliberately the same answer for "no such calendar" and "a
                // calendar the church does not share with members": a member
                // has no business learning which church calendars exist.
                throw new PortalSelfServiceException(
                    gettext('One of those calendars is not shared with members.')
                );
            }

            $accepted[$id] = true;
        }

        if ($accepted === []) {
            throw new PortalSelfServiceException(
                gettext('Choose at least one calendar to subscribe to.')
            );
        }

        if (self::token($user) === null) {
            $user->setPortalCalendarToken(self::mintToken());
        }

        $user->setPortalCalendarSelection(json_encode(array_keys($accepted)));
        $user->save();
    }

    /**
     * Rotate the token, leaving the selection alone. The address a member
     * shared by accident stops answering the moment this returns.
     */
    public static function reset(User $user): void
    {
        $user->setPortalCalendarToken(self::mintToken());
        $user->save();
    }

    /** The member's token, or null when they have never saved a selection. */
    public static function token(User $user): ?string
    {
        $token = trim((string) $user->getPortalCalendarToken());

        return $token === '' ? null : $token;
    }

    /**
     * The account a feed token belongs to, or null.
     *
     * One answer for three different situations — no such token, a token that
     * was rotated away, and an account whose feed was never created — because
     * the caller turns all three into the same 404 and must not be able to tell
     * them apart.
     */
    public static function findByToken(string $token): ?User
    {
        // A token is 64 hex characters. Refusing anything else before the query
        // keeps a probe with a megabyte of junk from reaching the database.
        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return null;
        }

        return UserQuery::create()->filterByPortalCalendarToken($token)->findOne();
    }

    /**
     * The events this member's feed serves right now.
     *
     * The window is fixed rather than caller-controlled: a subscription URL has
     * no query string a calendar app would fill in, and a fixed window means
     * every fetch costs the same.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function events(User $user): array
    {
        $wanted = self::servedKeys($user);
        if ($wanted === []) {
            return [];
        }

        $today = new DateTimeImmutable('today');
        $from = $today->modify('-' . self::WINDOW_MONTHS_BACK . ' months');
        $to = $today->modify('+' . self::WINDOW_MONTHS_AHEAD . ' months');

        $events = [];
        foreach (PortalCalendarService::eventsBetween($from, $to) as $event) {
            $props = is_array($event['extendedProps'] ?? null) ? $event['extendedProps'] : [];
            $id = self::id((string) ($props['calendarType'] ?? ''), (int) ($props['calendarId'] ?? -1));
            if (isset($wanted[$id])) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * The path the feed answers on, relative to the installation root.
     *
     * Null when the member has no token: there is no address to hand out, and
     * inventing one would imply a feed that does not exist.
     */
    public static function feedPath(User $user): ?string
    {
        $token = self::token($user);

        return $token === null ? null : '/api/public/portal-calendar/' . $token . '/calendar.ics';
    }

    /**
     * The composite id one calendar is known by, in the portal's API and in the
     * stored selection: the kind and the id, because a church calendar id and a
     * system calendar id are both small integers from different spaces.
     */
    public static function id(string $type, int $id): string
    {
        return $type . ':' . $id;
    }

    // ------------------------------------------------------------------ //

    /**
     * The stored selection as a set, without judging whether the calendars it
     * names are still shared — `choices()` is where that is decided.
     *
     * @return array<string, bool>
     */
    private static function selectionKeys(User $user): array
    {
        $raw = $user->getPortalCalendarSelection();
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return [];
        }

        $keys = [];
        foreach ($decoded as $entry) {
            if (is_string($entry) && $entry !== '') {
                $keys[$entry] = true;
            }
        }

        return $keys;
    }

    /**
     * What the feed is actually allowed to serve: the member's selection
     * narrowed to the calendars that are shared with members *now*.
     *
     * This is the intersection rule, and it is why un-sharing a calendar takes
     * effect immediately everywhere. A member who has never saved a selection
     * has no feed at all, so "nothing saved" is an empty set here rather than
     * the "everything" that `choices()` shows in the modal.
     *
     * @return array<string, bool>
     */
    private static function servedKeys(User $user): array
    {
        $selected = self::selectionKeys($user);
        if ($selected === []) {
            return [];
        }

        $served = [];
        foreach (PortalCalendarService::listChoices() as $choice) {
            if (!$choice['visible']) {
                continue;
            }
            $id = self::id($choice['type'], $choice['id']);
            if (isset($selected[$id])) {
                $served[$id] = true;
            }
        }

        return $served;
    }

    private static function mintToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }
}
