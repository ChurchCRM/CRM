<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PortalSigninAudit;
use ChurchCRM\model\ChurchCRM\PortalSigninAuditQuery;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Auth\SignInMethod;
use ChurchCRM\Portal\Auth\SignInReason;
use ChurchCRM\Portal\Permissions\PortalCapabilityRegistry;
use ChurchCRM\Portal\Permissions\PortalScope;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Reads the member sign-in audit for Admin → Member Portal → Audit (member
 * sign-in design §11.2): newest first, by person, date range and event. The
 * audit holds no secret, so every stored field may be shown to an administrator.
 */
final class SignInAuditService
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    /**
     * @param string|null $from first day, Y-m-d, in the church's time zone
     * @param string|null $to   last day, Y-m-d, inclusive
     *
     * @return array{total: int, filtered: int, rows: list<array<string, mixed>>}
     *
     * @throws InvalidArgumentException for a malformed date, a range that ends before it starts, or an unknown event
     */
    public function page(?int $personId, ?string $from, ?string $to, ?string $event, int $start = 0, int $length = self::DEFAULT_PAGE_SIZE): array
    {
        $start = max(0, $start);
        $length = max(1, min(self::MAX_PAGE_SIZE, $length));
        $fromDay = self::day($from);
        $toDay = self::day($to);
        if ($fromDay !== null && $toDay !== null && $toDay < $fromDay) {
            throw new InvalidArgumentException(gettext('The end date is before the start date.'));
        }
        $eventCase = null;
        if ($event !== null && $event !== '') {
            $eventCase = SignInAuditEvent::tryFrom($event) ?? throw new InvalidArgumentException(gettext('There is no such audit event.'));
        }

        $total = PortalSigninAuditQuery::create()->count();
        $query = PortalSigninAuditQuery::create();
        if ($personId !== null && $personId > 0) {
            $query->filterByPersonId($personId);
        }
        if ($fromDay !== null) {
            $query->filterByTime(['min' => $fromDay->format('Y-m-d 00:00:00')]);
        }
        if ($toDay !== null) {
            $query->filterByTime($toDay->modify('+1 day')->format('Y-m-d 00:00:00'), Criteria::LESS_THAN);
        }
        if ($eventCase !== null) {
            $query->filterByEvent($eventCase->value);
        }
        $filtered = (clone $query)->count();

        $entries = $query->orderByTime(Criteria::DESC)->orderById(Criteria::DESC)->offset($start)->limit($length)->find();

        $peopleIds = [];
        foreach ($entries as $entry) {
            foreach ([$entry->getPersonId(), $entry->getActorUserId(), ...self::matchedPersonIds((string) $entry->getDetail())] as $id) {
                if ($id !== null) {
                    $peopleIds[(int) $id] = true;
                }
            }
        }
        $names = [];
        if ($peopleIds !== []) {
            foreach (PersonQuery::create()->filterById(array_keys($peopleIds), Criteria::IN)->find() as $person) {
                $names[(int) $person->getId()] = $person->getFullName();
            }
        }

        $scopes = [];
        foreach ($entries as $entry) {
            $scope = self::detailScope((string) $entry->getEvent(), MemberSignInListService::parseDetail((string) $entry->getDetail()));
            if ($scope !== null) {
                $scopes[] = $scope;
            }
        }
        $scopeNames = PortalGrantService::scopeNames($scopes);

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = $this->row($entry, $names, $scopeNames);
        }

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function eventChoices(): array
    {
        return array_map(
            static fn (SignInAuditEvent $event): array => ['value' => $event->value, 'label' => $event->label()],
            SignInAuditEvent::cases(),
        );
    }

    /**
     * @param array<int, string>    $names
     * @param array<string, string> $scopeNames
     *
     * @return array<string, mixed>
     */
    private function row(PortalSigninAudit $entry, array $names, array $scopeNames): array
    {
        $event = SignInAuditEvent::tryFrom((string) $entry->getEvent());
        $method = $entry->getMethod() === null ? null : SignInMethod::tryFrom((string) $entry->getMethod());
        $reason = $entry->getReason() === null ? null : SignInReason::tryFrom((string) $entry->getReason());
        $personId = $entry->getPersonId() === null ? null : (int) $entry->getPersonId();
        $actorId = $entry->getActorUserId() === null ? null : (int) $entry->getActorUserId();
        $time = $entry->getTime();

        return [
            'id' => (int) $entry->getId(),
            'time' => $time instanceof DateTimeInterface ? $time->format('Y-m-d H:i:s') : null,
            'timeDisplay' => $time instanceof DateTimeInterface ? DateTimeUtils::formatDate($time, true) : '',
            'personId' => $personId,
            'personName' => $personId === null ? null : ($names[$personId] ?? null),
            'event' => (string) $entry->getEvent(),
            'eventLabel' => $event?->label() ?? (string) $entry->getEvent(),
            'method' => $entry->getMethod(),
            'methodLabel' => $method?->label() ?? $entry->getMethod(),
            'reason' => $entry->getReason(),
            'reasonLabel' => $reason?->label() ?? $entry->getReason(),
            'actorId' => $actorId,
            'actorName' => $actorId === null ? null : ($names[$actorId] ?? null),
            'email' => $entry->getEmail(),
            'detail' => $entry->getDetail(),
            'summary' => self::summary((string) $entry->getEvent(), (string) $entry->getDetail(), $names, $scopeNames),
        ];
    }

    /**
     * What a portal permission or family confirmation row was about, from its
     * `key=value` detail.
     *
     * @param array<string, string> $detail
     */
    private static function detailScope(string $event, array $detail): ?PortalScope
    {
        if (in_array($event, [SignInAuditEvent::GRANT_ADDED->value, SignInAuditEvent::GRANT_REMOVED->value], true) && isset($detail['scope'])) {
            [$type, $id] = array_pad(explode(':', $detail['scope'], 2), 2, '0');

            return new PortalScope($type, (int) $id);
        }
        if ($event === SignInAuditEvent::FAMILY_CONFIRMED->value && isset($detail['family'])) {
            return PortalScope::family((int) $detail['family']);
        }

        return null;
    }

    /**
     * The row's detail in plain words, where this page knows its shape.
     *
     * @param array<int, string>    $names
     * @param array<string, string> $scopeNames
     */
    private static function summary(string $event, string $rawDetail, array $names, array $scopeNames): ?string
    {
        if ($rawDetail === 'send_failed') {
            return gettext('The email could not be sent.');
        }
        if ($rawDetail === RememberedDeviceService::AUDIT_PREVIOUS_TOKEN) {
            return gettext('With the token the device replaced moments ago');
        }
        $matched = self::matchedPersonIds($rawDetail);
        if ($matched !== []) {
            return sprintf(
                gettext('Matches: %s'),
                implode(', ', array_map(static fn (int $id): string => $names[$id] ?? ('#' . $id), $matched)),
            );
        }

        $detail = MemberSignInListService::parseDetail($rawDetail);
        $scope = self::detailScope($event, $detail);
        $scopeName = $scope === null ? null : ($scopeNames[$scope->key()] ?? null);

        if ($event === SignInAuditEvent::FAMILY_CONFIRMED->value) {
            $result = ($detail['result'] ?? '') === PortalSelfService::CONFIRM_NO_CHANGE ? gettext('No changes') : gettext('Changes needed');

            return $scopeName === null ? $result : $scopeName . ' · ' . $result;
        }
        if ($scope === null || !isset($detail['capability'])) {
            return null;
        }

        $parts = [PortalCapabilityRegistry::get($detail['capability'])?->label ?? $detail['capability']];
        if ($scopeName !== null) {
            $parts[] = $scopeName;
        }
        if (isset($detail['until'])) {
            $parts[] = sprintf(gettext('until %s'), DateTimeUtils::formatDate($detail['until']));
        }

        return implode(' · ', $parts);
    }

    /**
     * The people a refused address matched, from the `matched:1,2` detail.
     *
     * @return int[]
     */
    private static function matchedPersonIds(string $detail): array
    {
        if (!str_starts_with($detail, 'matched:')) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', substr($detail, strlen('matched:')))), static fn (int $id): bool => $id > 0));
    }

    private static function day(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), DateTimeUtils::getConfiguredTimezone());
        if (!$day instanceof DateTimeImmutable || $day->format('Y-m-d') !== trim($value)) {
            throw new InvalidArgumentException(gettext('Dates must be written as YYYY-MM-DD.'));
        }

        return $day;
    }
}
