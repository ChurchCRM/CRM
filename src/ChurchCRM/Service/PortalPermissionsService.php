<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PortalGrantQuery;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use ChurchCRM\Portal\Auth\PortalSignInRules;
use ChurchCRM\Portal\Permissions\BulkPortalRule;
use ChurchCRM\Portal\Permissions\EffectiveGrant;
use ChurchCRM\Portal\Permissions\PortalAuthorizationService;
use ChurchCRM\Portal\Permissions\PortalCapability;
use ChurchCRM\Portal\Permissions\PortalCapabilityRegistry;
use ChurchCRM\Portal\Permissions\PortalRule;
use ChurchCRM\Portal\Permissions\PortalScope;
use ChurchCRM\Portal\Permissions\Rules\EveryoneRule;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use DateTimeImmutable;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;
use Throwable;

/**
 * Admin → Member Portal → Permissions (member sign-in design §8.6): every
 * capability, core and plugin, with its sources and how many people hold it,
 * and for one capability the holders and where each grant comes from.
 *
 * Holders are counted among the people who can open the portal: everyone the
 * blocks and the member rule let in, and staff, who reach it from their staff
 * sign-in. A rule that implements BulkPortalRule answers for all of them in a
 * few queries from its own data (the core rules: the people's family and role,
 * and the volunteer team scope rows); any other rule is asked person by person.
 */
final class PortalPermissionsService
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    /** @var array<string, array<int, list<array<string, mixed>>>>|null capability → person → grants */
    private ?array $holders = null;

    /** @var array<string, array<string, string>> capability → source key → label */
    private array $observedSources = [];

    /**
     * @return list<array<string, mixed>>
     */
    public function capabilities(): array
    {
        $holders = $this->holders();
        $rules = PortalCapabilityRegistry::activeRules();

        $rows = [];
        foreach (PortalAuthorizationService::describeCapabilities() as $capability) {
            $key = $capability['key'];
            $sources = [];
            foreach ($rules as $ruleKey => $rule) {
                if ($rule instanceof BulkPortalRule && in_array($key, $rule->getCapabilityKeys(), true)) {
                    $sources[$ruleKey] = $rule->getSourceName();
                }
            }
            $sources += $this->observedSources[$key] ?? [];

            $rows[] = $capability + [
                'ownerName' => PortalGrantService::ownerName($capability['owner']),
                'statusLabel' => PortalGrantService::statusLabel($capability['status']),
                'scopeLabel' => PortalGrantService::scopeTypeLabel($capability['scopeType']),
                'sources' => array_merge(
                    array_map(
                        static fn (string $ruleKey, string $label): array => [
                            'source' => $ruleKey === EveryoneRule::KEY ? EffectiveGrant::SOURCE_EVERYONE : EffectiveGrant::SOURCE_RULE,
                            'ruleKey' => $ruleKey,
                            'label' => $label,
                        ],
                        array_keys($sources),
                        array_values($sources),
                    ),
                    [['source' => EffectiveGrant::SOURCE_GIVEN, 'ruleKey' => null, 'label' => gettext('Given by an administrator')]],
                ),
                'holderCount' => count($holders[$key] ?? []),
                'givenCount' => $this->givenCount($key),
            ];
        }

        return $rows;
    }

    /**
     * One page of the people who hold a capability, with every grant they hold
     * it by.
     *
     * @return array{capability: array<string, mixed>, total: int, filtered: int, rows: list<array<string, mixed>>}
     *
     * @throws InvalidArgumentException for an unknown capability
     */
    public function holdersPage(string $capability, int $start = 0, int $length = self::DEFAULT_PAGE_SIZE, string $search = ''): array
    {
        $definition = PortalCapabilityRegistry::get($capability);
        if (!$definition instanceof PortalCapability) {
            throw new InvalidArgumentException(gettext('There is no such portal permission.'));
        }
        $start = max(0, $start);
        $length = max(1, min(self::MAX_PAGE_SIZE, $length));

        $byPerson = $this->holders()[$capability] ?? [];
        $total = count($byPerson);
        $filtered = 0;
        $rows = [];
        if ($byPerson !== []) {
            $query = PersonQuery::create()->filterById(array_keys($byPerson), Criteria::IN)
                ->joinFamily(null, Criteria::LEFT_JOIN)
                ->withColumn('Family.Name', 'FamilyName');
            $search = trim($search);
            if ($search !== '') {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $query->where(
                    "(Person.FirstName LIKE ? OR Person.LastName LIKE ? OR CONCAT(Person.FirstName, ' ', Person.LastName) LIKE ?)",
                    [$like, $like, $like],
                );
            }
            $filtered = $search === '' ? $total : (clone $query)->count();
            $people = $query->orderByLastName()->orderByFirstName()->orderById()->offset($start)->limit($length)->find();

            $scopes = [];
            foreach ($people as $person) {
                foreach ($byPerson[(int) $person->getId()] as $grant) {
                    $scopes[] = new PortalScope($grant['scope']['type'], $grant['scope']['id']);
                }
            }
            $scopeNames = PortalGrantService::scopeNames($scopes);

            foreach ($people as $person) {
                $grants = [];
                foreach ($byPerson[(int) $person->getId()] as $grant) {
                    $grant['scope']['name'] = $scopeNames[$grant['scope']['type'] . ':' . $grant['scope']['id']] ?? null;
                    $grants[] = $grant;
                }
                $rows[] = [
                    'personId' => (int) $person->getId(),
                    'name' => $person->getFullName(),
                    'familyId' => (int) $person->getFamId() > 0 ? (int) $person->getFamId() : null,
                    'familyName' => (string) $person->getVirtualColumn('FamilyName'),
                    'grants' => $grants,
                ];
            }
        }

        $summary = null;
        foreach ($this->capabilities() as $row) {
            if ($row['key'] === $capability) {
                $summary = $row;
            }
        }

        return ['capability' => $summary, 'total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    /**
     * The people who can open the portal: everyone the blocks and the member
     * rule let in, and every staff account the blocks let in.
     *
     * @return int[]
     */
    private function population(): array
    {
        $ids = [];
        foreach (MemberSignInRule::fromSettings()->maySignInQuery()->select(['Id'])->find() as $id) {
            $ids[(int) $id] = true;
        }
        foreach (PortalSignInRules::filterNotBlocked(PersonQuery::create())->joinUser()->select(['Id'])->find() as $id) {
            $ids[(int) $id] = true;
        }

        return array_keys($ids);
    }

    /**
     * @return array<string, array<int, list<array<string, mixed>>>>
     */
    private function holders(): array
    {
        if ($this->holders !== null) {
            return $this->holders;
        }

        $population = $this->population();
        $inPopulation = array_flip($population);
        $holders = [];

        foreach (PortalCapabilityRegistry::activeRules() as $ruleKey => $rule) {
            $owner = PortalCapabilityRegistry::ruleOwner($ruleKey);
            $source = $ruleKey === EveryoneRule::KEY ? EffectiveGrant::SOURCE_EVERYONE : EffectiveGrant::SOURCE_RULE;
            try {
                $grants = $rule instanceof BulkPortalRule ? $rule->grantsForPeople($population) : self::onePersonAtATime($rule, $population);
                foreach ($grants as $personId => $grant) {
                    if (!isset($inPopulation[$personId]) || !PortalAuthorizationService::fitsCatalog($grant->capability, $grant->scope, $owner)) {
                        continue;
                    }
                    $holders[$grant->capability][$personId][] = [
                        'source' => $source,
                        'sourceLabel' => $grant->label,
                        'ruleKey' => $ruleKey,
                        'grantId' => null,
                        'scope' => $grant->scope->toArray(),
                    ];
                    if (!$rule instanceof BulkPortalRule) {
                        $this->observedSources[$grant->capability][$ruleKey] = $ruleKey;
                    }
                }
            } catch (Throwable $e) {
                LoggerUtils::getAppLogger()->error('A portal rule failed while counting holders', ['rule' => $ruleKey, 'error' => $e->getMessage()]);
            }
        }

        $givers = [];
        foreach ($this->givenGrantsQuery()->find() as $row) {
            $personId = (int) $row->getPersonId();
            $scope = new PortalScope((string) $row->getScopeType(), (int) $row->getScopeId());
            if (!isset($inPopulation[$personId]) || !PortalAuthorizationService::fitsCatalog((string) $row->getCapability(), $scope)) {
                continue;
            }
            $giverId = $row->getGrantedByUserId() === null ? null : (int) $row->getGrantedByUserId();
            if ($giverId !== null && !array_key_exists($giverId, $givers)) {
                $givers[$giverId] = PersonQuery::create()->findPk($giverId)?->getFullName();
            }
            $giver = $giverId === null ? null : $givers[$giverId];
            $endsAt = $row->getEndsAt();
            $holders[(string) $row->getCapability()][$personId][] = [
                'source' => EffectiveGrant::SOURCE_GIVEN,
                'sourceLabel' => $endsAt === null
                    ? sprintf(gettext('Given by %s'), $giver ?? gettext('an administrator'))
                    : sprintf(gettext('Given by %1$s until %2$s'), $giver ?? gettext('an administrator'), DateTimeUtils::formatDate($endsAt)),
                'ruleKey' => null,
                'grantId' => (int) $row->getId(),
                'scope' => $scope->toArray(),
            ];
        }

        return $this->holders = $holders;
    }

    private function givenCount(string $capability): int
    {
        return $this->givenGrantsQuery()->filterByCapability($capability)->count();
    }

    private function givenGrantsQuery(): PortalGrantQuery
    {
        $now = (new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone()))->format('Y-m-d H:i:s');

        return PortalGrantQuery::create()
            ->condition('noEnd', 'PortalGrant.EndsAt IS NULL')
            ->condition('notEnded', 'PortalGrant.EndsAt > ?', $now)
            ->where(['noEnd', 'notEnded'], Criteria::LOGICAL_OR);
    }

    /**
     * For a rule without BulkPortalRule: each person, in batches.
     *
     * @param int[] $personIds
     *
     * @return iterable<int, \ChurchCRM\Portal\Permissions\PortalRuleGrant>
     */
    private static function onePersonAtATime(PortalRule $rule, array $personIds): iterable
    {
        foreach (array_chunk($personIds, 500) as $chunk) {
            foreach (PersonQuery::create()->filterById($chunk, Criteria::IN)->find() as $person) {
                foreach ($rule->grantsFor($person) as $grant) {
                    yield (int) $person->getId() => $grant;
                }
            }
        }
    }
}
