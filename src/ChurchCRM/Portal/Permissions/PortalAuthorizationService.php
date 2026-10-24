<?php

namespace ChurchCRM\Portal\Permissions;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PortalGrantQuery;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Portal\Permissions\Rules\EveryoneRule;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use DateTimeImmutable;
use DateTimeInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Throwable;

/**
 * Decides what a person may see and do in the portal (member sign-in design
 * §8.2, §8.3, §14 "Portal permissions"). Deny by default; a grant is always a
 * capability and a scope together, and two grants never combine.
 *
 * Grants come from the rules, evaluated live, and from `portal_grant_pgr`, and
 * are worked out once per person per request. A STAFF principal gets exactly
 * what the same person gets as a member: staff permissions add nothing here.
 *
 * The `...ForPerson()` methods answer the same questions for a person without a
 * session, for the admin pages and for code that holds a Person, not a principal.
 */
final class PortalAuthorizationService implements PortalAuthorization
{
    public const STATUS_ON = 'on';

    /** @var array<int, EffectiveGrant[]> */
    private static array $grantMemo = [];

    public function decide(PortalPrincipal $who, string $capability, ?PortalScope $on = null): Decision
    {
        return $this->decideForPerson($who->personId, $capability, $on);
    }

    public function scopesFor(PortalPrincipal $who, string $capability): array
    {
        return $this->scopesForPerson($who->personId, $capability);
    }

    public function decideForPerson(int $personId, string $capability, ?PortalScope $on = null): Decision
    {
        $definition = PortalCapabilityRegistry::get($capability);
        if (!$definition instanceof PortalCapability) {
            return Decision::deny(Decision::UNKNOWN_CAPABILITY);
        }

        $status = self::capabilityStatus($definition);
        if ($status !== self::STATUS_ON) {
            return Decision::deny($status);
        }

        if ($definition->isScoped() && !$on instanceof PortalScope) {
            return Decision::deny(Decision::SCOPE_REQUIRED);
        }
        $on ??= PortalScope::none();
        if ($on->type !== $definition->scopeType) {
            return Decision::deny(Decision::SCOPE_MISMATCH);
        }

        $grants = $this->grantsForPerson($personId);
        if ($grants === null) {
            return Decision::deny(Decision::NO_PERSON);
        }

        foreach ($grants as $grant) {
            if ($grant->capability === $capability && $grant->scope->equals($on)) {
                return Decision::allow($grant);
            }
        }

        return Decision::deny(Decision::NO_GRANT);
    }

    /**
     * @return PortalScope[]
     */
    public function scopesForPerson(int $personId, string $capability): array
    {
        $definition = PortalCapabilityRegistry::get($capability);
        if (!$definition instanceof PortalCapability || self::capabilityStatus($definition) !== self::STATUS_ON) {
            return [];
        }

        $scopes = [];
        foreach ($this->grantsForPerson($personId) ?? [] as $grant) {
            if ($grant->capability === $capability && $grant->scope->type === $definition->scopeType) {
                $scopes[$grant->scope->key()] ??= $grant->scope;
            }
        }

        return array_values($scopes);
    }

    /**
     * @return int[]
     */
    public function scopeIdsForPerson(int $personId, string $capability): array
    {
        return array_map(static fn (PortalScope $scope): int => $scope->id, $this->scopesForPerson($personId, $capability));
    }

    /**
     * Every grant the person holds, with its source label, for capabilities the
     * registry knows, whether or not the capability is switched on right now.
     * Row 9's person card and Permissions tab read this.
     *
     * @return EffectiveGrant[]
     */
    public function effectiveGrantsForPerson(int $personId): array
    {
        return $this->grantsForPerson($personId) ?? [];
    }

    /**
     * 'on', or the Decision reason that switches the capability off for everyone.
     */
    public static function capabilityStatus(PortalCapability $capability): string
    {
        if (!PortalCapabilityRegistry::isOwnerActive($capability->owner)) {
            return Decision::OWNER_OFF;
        }
        if ($capability->featureSwitch !== null && !self::featureIsOn($capability->featureSwitch)) {
            return Decision::FEATURE_OFF;
        }
        if (!$capability->passesAvailability()) {
            return Decision::FEATURE_OFF;
        }

        return self::STATUS_ON;
    }

    /**
     * The catalog with its metadata, for the admin Permissions tab.
     *
     * @return array<int, array{key: string, label: string, description: string, owner: string, scopeType: string, featureSwitch: string|null, status: string}>
     */
    public static function describeCapabilities(): array
    {
        $rows = [];
        foreach (PortalCapabilityRegistry::all() as $capability) {
            $rows[] = [
                'key' => $capability->key,
                'label' => $capability->label,
                'description' => $capability->description,
                'owner' => $capability->owner,
                'scopeType' => $capability->scopeType,
                'featureSwitch' => $capability->featureSwitch,
                'status' => self::capabilityStatus($capability),
            ];
        }

        return $rows;
    }

    /** Drop what this request has worked out, after a change to grants or church data. */
    public static function forget(?int $personId = null): void
    {
        if ($personId === null) {
            self::$grantMemo = [];

            return;
        }
        unset(self::$grantMemo[$personId]);
    }

    /**
     * @return EffectiveGrant[]|null null when there is no such person
     */
    private function grantsForPerson(int $personId): ?array
    {
        if (array_key_exists($personId, self::$grantMemo)) {
            return self::$grantMemo[$personId];
        }

        $person = PersonQuery::create()->findPk($personId);
        if (!$person instanceof Person) {
            return null;
        }

        $grants = [];
        foreach (PortalCapabilityRegistry::activeRules() as $ruleKey => $rule) {
            $source = $ruleKey === EveryoneRule::KEY ? EffectiveGrant::SOURCE_EVERYONE : EffectiveGrant::SOURCE_RULE;
            $owner = PortalCapabilityRegistry::ruleOwner($ruleKey);
            try {
                foreach ($rule->grantsFor($person) as $ruleGrant) {
                    if (self::fitsCatalog($ruleGrant->capability, $ruleGrant->scope, $owner)) {
                        $grants[] = new EffectiveGrant($ruleGrant->capability, $ruleGrant->scope, $source, $ruleGrant->label, $ruleKey);
                    }
                }
            } catch (Throwable $e) {
                LoggerUtils::getAppLogger()->error('A portal rule failed; it grants nothing this request', [
                    'rule' => $ruleKey,
                    'personId' => $personId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($this->givenGrants($personId) as $grant) {
            $grants[] = $grant;
        }

        return self::$grantMemo[$personId] = $grants;
    }

    /**
     * The grants administrators gave this person that have not ended yet.
     *
     * @return EffectiveGrant[]
     */
    private function givenGrants(int $personId): array
    {
        $now = new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
        $rows = PortalGrantQuery::create()
            ->filterByPersonId($personId)
            ->condition('noEnd', 'PortalGrant.EndsAt IS NULL')
            ->condition('notEnded', 'PortalGrant.EndsAt > ?', $now->format('Y-m-d H:i:s'))
            ->where(['noEnd', 'notEnded'], Criteria::LOGICAL_OR)
            ->orderById()
            ->find();

        $givers = [];
        $grants = [];
        foreach ($rows as $row) {
            $scope = new PortalScope((string) $row->getScopeType(), (int) $row->getScopeId());
            if (!self::fitsCatalog((string) $row->getCapability(), $scope)) {
                continue;
            }

            $giverId = $row->getGrantedByUserId() === null ? null : (int) $row->getGrantedByUserId();
            if ($giverId !== null && !array_key_exists($giverId, $givers)) {
                $givers[$giverId] = PersonQuery::create()->findPk($giverId)?->getFullName();
            }
            $giver = $giverId === null ? null : $givers[$giverId];
            $endsAt = $row->getEndsAt();

            $grants[] = new EffectiveGrant(
                (string) $row->getCapability(),
                $scope,
                EffectiveGrant::SOURCE_GIVEN,
                self::givenLabel($giver, $endsAt),
                null,
                (int) $row->getId(),
                $giverId,
                $endsAt,
            );
        }

        return $grants;
    }

    private static function givenLabel(?string $giver, ?DateTimeInterface $endsAt): string
    {
        $giver ??= gettext('an administrator');
        if ($endsAt === null) {
            return sprintf(gettext('Given by %s'), $giver);
        }

        return sprintf(gettext('Given by %1$s until %2$s'), $giver, DateTimeUtils::formatDate($endsAt));
    }

    /**
     * A grant counts only for a known capability on a scope of its type. A
     * plugin's rule may give only that plugin's own capabilities.
     */
    public static function fitsCatalog(string $capability, PortalScope $scope, ?string $ruleOwner = null): bool
    {
        $definition = PortalCapabilityRegistry::get($capability);

        return $definition instanceof PortalCapability
            && $definition->scopeType === $scope->type
            && ($ruleOwner === null || $ruleOwner === PortalCapability::OWNER_CORE || $ruleOwner === $definition->owner);
    }

    private static function featureIsOn(string $setting): bool
    {
        try {
            return SystemConfig::getBooleanValue($setting);
        } catch (Throwable) {
            return false;
        }
    }
}
