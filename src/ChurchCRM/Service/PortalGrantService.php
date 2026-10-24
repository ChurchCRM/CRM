<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PortalGrant;
use ChurchCRM\model\ChurchCRM\PortalGrantQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Portal\Auth\SignInAudit;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Permissions\Decision;
use ChurchCRM\Portal\Permissions\PortalAuthorizationService;
use ChurchCRM\Portal\Permissions\PortalCapability;
use ChurchCRM\Portal\Permissions\PortalCapabilityRegistry;
use ChurchCRM\Portal\Permissions\PortalScope;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Portal permissions an administrator gives one person (member sign-in design
 * §8.3 source 3, §8.6): a capability, a scope, an optional last day and a
 * reason. Every grant and removal is audited with the administrator, and the
 * person's worked-out grants are forgotten so the change applies at once.
 *
 * The reason stays on the grant; the audit records only what was given, on
 * what, and until when (§14).
 */
final class PortalGrantService
{
    public const REASON_MAX_LENGTH = 255;
    public const SCOPE_CHOICE_LIMIT = 25;

    /** Scope types the person page can pick a scope for. */
    public const GIVABLE_SCOPE_TYPES = [
        PortalScope::NONE,
        PortalScope::FAMILY,
        PortalScope::TEAM,
        PortalScope::MINISTRY,
        PortalScope::GROUP,
    ];

    /**
     * @throws InvalidArgumentException 400: an unknown capability, a missing or unknown scope, a bad date or reason
     * @throws DomainException          409: the person already holds this capability on this scope, from any source
     */
    public function give(Person $person, User $actor, string $capability, ?int $scopeId, ?string $endsOn, string $reason): PortalGrant
    {
        $definition = PortalCapabilityRegistry::get($capability);
        if (!$definition instanceof PortalCapability) {
            throw new InvalidArgumentException(gettext('There is no such portal permission.'));
        }
        if (!in_array($definition->scopeType, self::GIVABLE_SCOPE_TYPES, true)) {
            throw new InvalidArgumentException(gettext('This permission cannot be given on the person page.'));
        }

        $scope = $definition->isScoped() ? new PortalScope($definition->scopeType, (int) $scopeId) : PortalScope::none();
        if ($definition->isScoped() && ($scope->id <= 0 || self::scopeNames([$scope]) === [])) {
            throw new InvalidArgumentException(gettext('Choose what the permission is for.'));
        }

        $endsAt = self::endOfDay($endsOn);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(gettext('Say why this permission is being given.'));
        }
        if (mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(gettext('The reason can be at most %d characters long.'), self::REASON_MAX_LENGTH));
        }

        $personId = (int) $person->getId();
        foreach ((new PortalAuthorizationService())->effectiveGrantsForPerson($personId) as $held) {
            if ($held->capability === $capability && $held->scope->equals($scope)) {
                throw new DomainException(gettext('This person already has this permission.'), 409);
            }
        }

        // A grant that ended is given again on the same row.
        $now = self::now();
        $grant = PortalGrantQuery::create()
            ->filterByPersonId($personId)
            ->filterByCapability($capability)
            ->filterByScopeType($scope->type)
            ->filterByScopeId($scope->id)
            ->findOne();
        $grant ??= (new PortalGrant())
            ->setPersonId($personId)
            ->setCapability($capability)
            ->setScopeType($scope->type)
            ->setScopeId($scope->id);
        $grant->setEndsAt($endsAt)
            ->setReason($reason)
            ->setGrantedByUserId((int) $actor->getId())
            ->setGrantedAt($now)
            ->save();

        PortalAuthorizationService::forget($personId);
        SignInAudit::record(
            SignInAuditEvent::GRANT_ADDED,
            $personId,
            actorUserId: (int) $actor->getId(),
            detail: self::auditDetail($grant),
        );

        return $grant;
    }

    /**
     * @throws DomainException 404 when the person holds no given grant with this id
     */
    public function remove(Person $person, User $actor, int $grantId): void
    {
        $personId = (int) $person->getId();
        $grant = PortalGrantQuery::create()->filterByPersonId($personId)->findPk($grantId);
        if (!$grant instanceof PortalGrant) {
            throw new DomainException(gettext('This person has no such permission.'), 404);
        }

        $detail = self::auditDetail($grant);
        $grant->delete();

        PortalAuthorizationService::forget($personId);
        SignInAudit::record(SignInAuditEvent::GRANT_REMOVED, $personId, actorUserId: (int) $actor->getId(), detail: $detail);
    }

    /**
     * Every capability an administrator can give here, for the picker.
     *
     * @return list<array<string, mixed>>
     */
    public static function givableCapabilities(): array
    {
        $choices = [];
        foreach (PortalAuthorizationService::describeCapabilities() as $capability) {
            if (in_array($capability['scopeType'], self::GIVABLE_SCOPE_TYPES, true)) {
                $choices[] = $capability + [
                    'ownerName' => self::ownerName($capability['owner']),
                    'statusLabel' => self::statusLabel($capability['status']),
                    'scopeLabel' => self::scopeTypeLabel($capability['scopeType']),
                    'scopeFieldLabel' => match ($capability['scopeType']) {
                        PortalScope::FAMILY => gettext('Family'),
                        PortalScope::TEAM => gettext('Team'),
                        PortalScope::MINISTRY => gettext('Ministry'),
                        PortalScope::GROUP => gettext('Group'),
                        default => '',
                    },
                ];
            }
        }

        return $choices;
    }

    /**
     * What the person page offers to give this person: not what they already
     * hold from any source. A scoped capability lists the scopes they hold in
     * `heldScopeIds` for the picker to leave out, until they hold all it offers.
     *
     * @return list<array<string, mixed>>
     */
    public static function givableCapabilitiesFor(int $personId): array
    {
        $held = [];
        foreach ((new PortalAuthorizationService())->effectiveGrantsForPerson($personId) as $grant) {
            $held[$grant->capability][$grant->scope->id] = $grant->scope->id;
        }

        $offeredCounts = [];
        $choices = [];
        foreach (self::givableCapabilities() as $choice) {
            $heldIds = array_values($held[$choice['key']] ?? []);
            if ($heldIds !== [] && ($choice['scopeType'] === PortalScope::NONE || self::holdsEveryScope($choice['scopeType'], $heldIds, $offeredCounts))) {
                continue;
            }
            $choices[] = $choice + ['heldScopeIds' => $heldIds];
        }

        return $choices;
    }

    /**
     * The given grants stored for a person, in effect or not, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function storedGrantsFor(int $personId): array
    {
        $rows = PortalGrantQuery::create()->filterByPersonId($personId)->orderByGrantedAt(Criteria::DESC)->orderById(Criteria::DESC)->find();
        $scopes = [];
        foreach ($rows as $row) {
            $scopes[] = new PortalScope((string) $row->getScopeType(), (int) $row->getScopeId());
        }
        $names = self::scopeNames($scopes);
        $now = self::now();

        $grants = [];
        foreach ($rows as $row) {
            $definition = PortalCapabilityRegistry::get((string) $row->getCapability());
            $scope = new PortalScope((string) $row->getScopeType(), (int) $row->getScopeId());
            $endsAt = $row->getEndsAt();
            $grants[] = [
                'grantId' => (int) $row->getId(),
                'capability' => (string) $row->getCapability(),
                'capabilityLabel' => $definition?->label ?? (string) $row->getCapability(),
                'known' => $definition instanceof PortalCapability,
                'scope' => $scope->toArray() + ['name' => $names[$scope->key()] ?? null],
                'endsAt' => $endsAt?->format('Y-m-d H:i:s'),
                'endsOnDisplay' => $endsAt === null ? null : DateTimeUtils::formatDate($endsAt),
                'ended' => !self::isInEffect($row, $now),
                'reason' => $row->getReason(),
                'grantedByUserId' => $row->getGrantedByUserId() === null ? null : (int) $row->getGrantedByUserId(),
                'grantedAt' => $row->getGrantedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $grants;
    }

    /**
     * Families, teams, ministries or groups whose name contains the query, for
     * the scope picker, leaving out the ids in `$excludeIds`.
     *
     * @param int[] $excludeIds
     *
     * @return list<array{id: int, text: string}>
     *
     * @throws InvalidArgumentException for a scope type the picker does not offer
     */
    public function scopeChoices(string $type, string $query, array $excludeIds = []): array
    {
        $like = '%' . addcslashes(trim($query), '%_\\') . '%';
        $scopes = self::offeredScopes($type)->filterByName($like, Criteria::LIKE);
        if ($excludeIds !== []) {
            $scopes->filterById($excludeIds, Criteria::NOT_IN);
        }
        if ($type === PortalScope::TEAM) {
            $scopes->withColumn('Ministry.Name', 'MinistryName')->orderBy('MinistryName');
        }

        $choices = [];
        foreach ($scopes->orderByName()->limit(self::SCOPE_CHOICE_LIMIT)->find() as $scope) {
            $text = (string) $scope->getName();
            if ($type === PortalScope::FAMILY) {
                $city = trim((string) $scope->getCity());
                $text .= $city === '' ? '' : ' (' . $city . ')';
            } elseif ($type === PortalScope::TEAM) {
                $text = sprintf('%s (%s)', $text, $scope->getVirtualColumn('MinistryName'));
            }
            $choices[] = ['id' => (int) $scope->getId(), 'text' => $text];
        }

        return $choices;
    }

    /**
     * What the scope picker can offer for a scope type, before any search.
     *
     * @throws InvalidArgumentException for a scope type the picker does not offer
     */
    private static function offeredScopes(string $type): FamilyQuery|VolunteerTeamQuery|VolunteerMinistryQuery|GroupQuery
    {
        return match ($type) {
            PortalScope::FAMILY => FamilyQuery::create()->filterByDateDeactivated(null, Criteria::ISNULL),
            PortalScope::TEAM => VolunteerTeamQuery::create()->joinMinistry(),
            PortalScope::MINISTRY => VolunteerMinistryQuery::create(),
            PortalScope::GROUP => GroupQuery::create(),
            default => throw new InvalidArgumentException(gettext('There is no picker for this kind of scope.')),
        };
    }

    /**
     * Whether the held ids cover every scope the picker offers for the type.
     * Counting what the picker offers first keeps this to one query per type
     * for anyone who holds fewer scopes than that.
     *
     * @param int[]              $heldIds
     * @param array<string, int> $offeredCounts filled in per scope type
     */
    private static function holdsEveryScope(string $type, array $heldIds, array &$offeredCounts): bool
    {
        $offeredCounts[$type] ??= self::offeredScopes($type)->count();
        if ($offeredCounts[$type] > count($heldIds)) {
            return false;
        }

        return self::offeredScopes($type)->filterById($heldIds, Criteria::NOT_IN)->count() === 0;
    }

    /**
     * The name of each scope that still exists, by `PortalScope::key()`. A
     * scope of type `none` has no name; one of a type core does not know is
     * left out.
     *
     * @param PortalScope[] $scopes
     *
     * @return array<string, string>
     */
    public static function scopeNames(array $scopes): array
    {
        $ids = [];
        foreach ($scopes as $scope) {
            if ($scope->type !== PortalScope::NONE && $scope->id > 0) {
                $ids[$scope->type][$scope->id] = $scope->id;
            }
        }

        $names = [];
        foreach ($ids as $type => $typeIds) {
            $query = match ($type) {
                PortalScope::FAMILY => FamilyQuery::create(),
                PortalScope::TEAM => VolunteerTeamQuery::create(),
                PortalScope::MINISTRY => VolunteerMinistryQuery::create(),
                PortalScope::GROUP => GroupQuery::create(),
                default => null,
            };
            if ($query === null) {
                continue;
            }
            foreach ($query->filterById(array_values($typeIds), Criteria::IN)->select(['Id', 'Name'])->find() as $row) {
                $names[$type . ':' . (int) $row['Id']] = (string) $row['Name'];
            }
        }

        return $names;
    }

    public static function ownerName(string $owner): string
    {
        if ($owner === PortalCapability::OWNER_CORE) {
            return 'ChurchCRM';
        }

        return PluginManager::getPluginMetadata($owner)?->getName() ?? $owner;
    }

    public static function statusLabel(string $status): ?string
    {
        return match ($status) {
            PortalAuthorizationService::STATUS_ON => null,
            Decision::OWNER_OFF => gettext('Off for everyone: its plugin is turned off'),
            default => gettext('Off for everyone: its feature is switched off'),
        };
    }

    public static function scopeTypeLabel(string $type): string
    {
        return match ($type) {
            PortalScope::NONE => gettext('Everywhere'),
            PortalScope::FAMILY => gettext('A family'),
            PortalScope::TEAM => gettext('A team'),
            PortalScope::MINISTRY => gettext('A ministry'),
            PortalScope::GROUP => gettext('A group'),
            default => $type,
        };
    }

    private static function isInEffect(PortalGrant $grant, DateTimeImmutable $now): bool
    {
        $endsAt = $grant->getEndsAt();

        return $endsAt === null || $endsAt->getTimestamp() > $now->getTimestamp();
    }

    /**
     * The last day a grant counts, inclusive: it ends at that day's last second.
     *
     * @throws InvalidArgumentException for a malformed date or one in the past
     */
    private static function endOfDay(?string $endsOn): ?DateTimeImmutable
    {
        $endsOn = trim((string) $endsOn);
        if ($endsOn === '') {
            return null;
        }
        $timezone = DateTimeUtils::getConfiguredTimezone();
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $endsOn, $timezone);
        if (!$day instanceof DateTimeImmutable || $day->format('Y-m-d') !== $endsOn) {
            throw new InvalidArgumentException(gettext('Dates must be written as YYYY-MM-DD.'));
        }
        if ($day < new DateTimeImmutable('today', $timezone)) {
            throw new InvalidArgumentException(gettext('The last day cannot be in the past.'));
        }

        return $day->setTime(23, 59, 59);
    }

    private static function auditDetail(PortalGrant $grant): string
    {
        $parts = [
            'grant=' . (int) $grant->getId(),
            'capability=' . $grant->getCapability(),
            'scope=' . $grant->getScopeType() . ':' . (int) $grant->getScopeId(),
        ];
        if ($grant->getEndsAt() !== null) {
            $parts[] = 'until=' . $grant->getEndsAt()->format('Y-m-d');
        }

        return implode('; ', $parts);
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
    }
}
