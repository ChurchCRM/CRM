<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\Map\VolunteerMinistryTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunityQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunity;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunityQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Utils\VersionUtils;
use ChurchCRM\Volunteer\Service\VolunteerEventService;
use ChurchCRM\Volunteer\Service\VolunteerMinistryService;
use ChurchCRM\Volunteer\Service\VolunteerQualificationService;
use ChurchCRM\Volunteer\VolunteerException;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

/**
 * The legacy Volunteer Opportunities (V1) on their way out (#10606, epic #9702).
 *
 * V1 is "in use" while the install runs V1 or Both, holds opportunities nobody has
 * converted yet, or has people linked to a converted one since who wait for their
 * qualification; everything V1 shows hides otherwise. Conversion is one way: each
 * opportunity becomes a position (or, by choice, a ministry of its own) and each person
 * linked to it a qualification for that position. It goes through the V2 services, sends
 * no email, leaves the V1 rows as they are, and records what became of each opportunity
 * in `aVolunteerV1Conversion`, so running it again converts only what is new: new
 * opportunities, and people linked to a converted one since, who have no qualification
 * for its position yet.
 */
class VolunteerV1Service
{
    public const MAP_CONFIG = 'aVolunteerV1Conversion';
    public const NOTICE_CONFIG = 'sVolunteerV1NoticeDismissed';

    public const SKIP_MISSING = 'missing';
    public const SKIP_DECEASED = 'deceased';

    private static ?bool $inUse = null;

    public static function isInUse(): bool
    {
        return self::$inUse ??= User::isVolunteerV1Enabled() || self::unconvertedCount() > 0 || self::pendingCount() > 0;
    }

    public static function unconvertedCount(): int
    {
        return count(self::unconvertedIds());
    }

    /** People linked to a converted opportunity since its conversion, not yet qualified for its position. */
    public static function pendingCount(): int
    {
        $map = self::conversionMap(self::storedConversion());
        if ($map === []) {
            return 0;
        }
        $service = new self();
        $links = $service->linkedPeople(array_keys($map));
        $qualified = $service->qualificationsOf(array_values($map));
        $pending = 0;
        foreach ($map as $opportunityId => $positionId) {
            $pending += count(self::pendingPeople($links[$opportunityId][0], $qualified[$positionId] ?? null));
        }

        return $pending;
    }

    /** @return int[] */
    private static function unconvertedIds(): array
    {
        $converted = array_keys(self::conversionMap(self::storedConversion()));
        $query = VolunteerOpportunityQuery::create();
        if ($converted !== []) {
            $query->filterById($converted, Criteria::NOT_IN);
        }

        return array_map('intval', $query->select(['Id'])->find()->toArray());
    }

    /**
     * Opportunity id => the id of the position it became.
     *
     * @param array<string, mixed> $stored the decoded `aVolunteerV1Conversion`
     *
     * @return array<int, int>
     */
    public static function conversionMap(array $stored): array
    {
        $map = [];
        foreach (is_array($stored['opportunities'] ?? null) ? $stored['opportunities'] : [] as $opportunityId => $positionId) {
            $map[(int) $opportunityId] = (int) $positionId;
        }

        return $map;
    }

    /**
     * The ministry the last conversion filled, when it still exists; 0 otherwise.
     *
     * @param array<string, mixed> $stored the decoded `aVolunteerV1Conversion`
     */
    public static function previousMinistryId(array $stored): int
    {
        $id = (int) ($stored['ministryId'] ?? 0);

        return $id > 0 && VolunteerMinistryQuery::create()->filterById($id)->exists() ? $id : 0;
    }

    /**
     * `aVolunteerV1Conversion` decoded. `$value` is the row read inside a conversion's lock;
     * without it, the value this request loaded.
     *
     * @return array<string, mixed>
     */
    public static function storedConversion(?string $value = null): array
    {
        $stored = json_decode($value ?? (string) SystemConfig::getValue(self::MAP_CONFIG), true);

        return is_array($stored) ? $stored : [];
    }

    /**
     * What every retirement notice says: how much is left to convert and who it concerns.
     *
     * @return array{unconverted: int, people: int, pending: int, version: string}
     */
    public static function noticeFacts(): array
    {
        return [
            'unconverted' => self::unconvertedCount(),
            'people' => self::linkedPeopleCount(),
            'pending' => self::pendingCount(),
            'version' => User::getVolunteerVersion(),
        ];
    }

    /**
     * The Admin Dashboard notice's facts, or null when V1 is not in use or an administrator
     * dismissed the notice on this version.
     *
     * @return array{unconverted: int, people: int, pending: int, version: string}|null
     */
    public static function dashboardNotice(): ?array
    {
        if (!self::isInUse() || SystemConfig::getValue(self::NOTICE_CONFIG) === VersionUtils::getInstalledVersion()) {
            return null;
        }

        return self::noticeFacts();
    }

    public static function dismissDashboardNotice(): void
    {
        SystemConfig::setValue(self::NOTICE_CONFIG, VersionUtils::getInstalledVersion());
    }

    /** People linked to an opportunity that is still to be converted, each once. */
    public static function linkedPeopleCount(): int
    {
        $ids = self::unconvertedIds();
        if ($ids === []) {
            return 0;
        }

        return count(array_unique(PersonVolunteerOpportunityQuery::create()
            ->filterByVolunteerOpportunityId($ids, Criteria::IN)
            ->select(['PersonId'])
            ->find()
            ->toArray()));
    }

    /**
     * What converting with this choice would do. Nothing is written. `revision` identifies
     * this plan; convert() runs only the plan with that revision.
     *
     * @param array<string, mixed> $choice `ministryId` (an existing ministry) or `ministryName`
     *                                     (a new one), and `ownMinistry`: opportunity ids that
     *                                     become ministries of their own
     *
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public function preview(array $choice): array
    {
        $plan = $this->plan($choice, self::storedConversion());

        return $plan + ['revision' => self::revisionOf($plan)];
    }

    /**
     * @param array<string, mixed> $plan
     */
    private static function revisionOf(array $plan): string
    {
        return hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $choice
     * @param array<string, mixed> $stored
     *
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function plan(array $choice, array $stored): array
    {
        [$target, $targetName, $ownMinistry] = $this->readChoice($choice, $stored);
        $map = self::conversionMap($stored);
        $teamNames = $target === null ? [] : $this->positionNamesOf($this->firstTeam($target));
        $ministryNames = $this->ministryNames();
        if ($target === null) {
            $ministryNames[mb_strtolower($targetName)] = true;
        }

        $opportunities = [];
        $totals = ['positions' => 0, 'ministries' => 0, 'qualifications' => 0, 'skipped' => 0, 'converted' => 0, 'pending' => 0];
        $rows = iterator_to_array(VolunteerOpportunityQuery::create()->orderByOrder()->orderById()->find(), false);
        $links = $this->linkedPeople(array_map(static fn (VolunteerOpportunity $row): int => (int) $row->getId(), $rows));
        $qualified = $this->qualificationsOf(array_values($map));
        foreach ($rows as $opportunity) {
            $id = (int) $opportunity->getId();
            [$people, $skipped] = $links[$id];
            $converted = isset($map[$id]);
            $ownsMinistry = !$converted && in_array($id, $ownMinistry, true);
            $baseName = $this->baseName($opportunity);
            $newName = $converted ? '' : ($ownsMinistry ? $this->freeName($baseName, $ministryNames) : $this->freeName($baseName, $teamNames));
            $pending = $converted ? self::pendingPeople($people, $qualified[$map[$id]] ?? null) : [];

            $opportunities[] = [
                'id' => $id,
                'name' => (string) $opportunity->getName(),
                'description' => (string) $opportunity->getDescription(),
                'active' => $opportunity->getActive() !== 'false',
                'order' => (int) $opportunity->getOrder(),
                'converted' => $converted,
                'becomes' => $ownsMinistry ? 'ministry' : 'position',
                'newName' => $newName,
                'renamed' => !$converted && $newName !== trim((string) $opportunity->getName()),
                'people' => $people,
                'skipped' => $skipped,
                'pending' => $pending,
            ];

            if ($converted) {
                ++$totals['converted'];
                $totals['pending'] += count($pending);
                continue;
            }
            ++$totals[$ownsMinistry ? 'ministries' : 'positions'];
            $totals['qualifications'] += count($people);
            $totals['skipped'] += count($skipped);
        }

        return [
            'version' => User::getVolunteerVersion(),
            'target' => ['id' => $target?->getId(), 'name' => $target?->getName() ?? $targetName, 'exists' => $target !== null],
            'opportunities' => $opportunities,
            'totals' => $totals,
        ];
    }

    /**
     * Convert every opportunity not converted yet, and qualify the people linked to a
     * converted one since, in one transaction. An install on V1 moves to Both first,
     * because the result is V2 data. With nothing to do, nothing is written.
     *
     * @param array<string, mixed> $choice   as for preview()
     * @param string               $revision the revision of the preview the administrator confirmed
     *
     * @throws InvalidArgumentException when the choice is malformed
     * @throws VolunteerException       409 when the plan is no longer the confirmed one; nothing is written
     * @throws \Throwable               when the conversion fails; nothing is kept
     *
     * @return array<string, mixed> the preview of what was done, plus the new map
     */
    public function convert(array $choice, string $revision, User $actor): array
    {
        $previousVersion = User::getVolunteerVersion();
        $connection = Propel::getWriteConnection(VolunteerMinistryTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            // Two administrators converting at once would both plan from the same map; the
            // second waits here and plans from what the first committed. The map is read as a
            // scalar because the Config object this request loaded at startup is pooled.
            VolunteerOpportunityQuery::create()->lockForUpdate()->find($connection);
            $stored = self::storedConversion(ConfigQuery::create()->filterByName(self::MAP_CONFIG)->select('Value')->findOne($connection) ?? '');
            $plan = $this->plan($choice, $stored);
            if (!hash_equals(self::revisionOf($plan), $revision)) {
                throw new VolunteerException(gettext('The Volunteer Opportunities changed since the preview. Check the preview again, then convert.'), 409);
            }
            $map = self::conversionMap($stored);
            if ($plan['totals']['positions'] + $plan['totals']['ministries'] + $plan['totals']['pending'] === 0) {
                $connection->commit();

                return $plan + ['map' => $map];
            }

            if ($previousVersion === 'v1') {
                SystemConfig::setValue('sVolunteerVersion', 'both');
                VolunteerEventService::addOtherEventType();
            }
            $ministries = new VolunteerMinistryService();
            $qualifications = new VolunteerQualificationService($ministries->getAuthorizationService(), $ministries);

            $target = $plan['target']['exists']
                ? VolunteerMinistryQuery::create()->findPk($plan['target']['id'])
                : $ministries->createMinistry($plan['target']['name'], null, $actor);
            $targetTeam = $this->firstTeam($target);

            foreach ($plan['opportunities'] as $opportunity) {
                if ($opportunity['converted']) {
                    if ($opportunity['pending'] !== []) {
                        $position = VolunteerPositionQuery::create()->findPk($map[$opportunity['id']]);
                        $qualifications->importQualifications(array_column($opportunity['pending'], 'id'), $position, $actor);
                    }
                    continue;
                }
                [$ministry, $team] = $opportunity['becomes'] === 'ministry'
                    ? $this->ownMinistry($ministries->createMinistry($opportunity['newName'], $opportunity['description'] ?: null, $actor))
                    : [$target, $targetTeam];
                $position = $ministries->createPosition(
                    $ministry,
                    $team,
                    $opportunity['newName'],
                    $opportunity['description'] ?: null,
                    $opportunity['order'],
                    $actor,
                    false,
                    false,
                    $opportunity['active']
                );
                $qualifications->importQualifications(array_column($opportunity['people'], 'id'), $position, $actor);
                $map[$opportunity['id']] = (int) $position->getId();
            }

            SystemConfig::setValue(self::MAP_CONFIG, json_encode(['ministryId' => (int) $target->getId(), 'opportunities' => $map]));
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            if ($previousVersion === 'v1') {
                SystemConfig::setValue('sVolunteerVersion', 'v1');
            }

            throw $e;
        }

        self::$inUse = null;

        return $plan + ['map' => $map];
    }

    /**
     * @param array<string, mixed> $choice
     * @param array<string, mixed> $stored
     *
     * @throws InvalidArgumentException
     *
     * @return array{0: VolunteerMinistry|null, 1: string, 2: int[]}
     */
    private function readChoice(array $choice, array $stored): array
    {
        $ownMinistry = $choice['ownMinistry'] ?? [];
        if (!is_array($ownMinistry)) {
            throw new InvalidArgumentException(gettext('Send the opportunities that become ministries as a list of ids.'));
        }
        $ownMinistryIds = [];
        foreach ($ownMinistry as $value) {
            $id = self::wholeNumber($value);
            if ($id === null || $id === 0) {
                throw new InvalidArgumentException(gettext('Send the opportunities that become ministries as a list of ids.'));
            }
            $ownMinistryIds[] = $id;
        }

        $ministryId = isset($choice['ministryId']) ? self::wholeNumber($choice['ministryId']) : 0;
        if ($ministryId === null) {
            throw new InvalidArgumentException(gettext('That ministry does not exist.'));
        }
        if (isset($choice['ministryName']) && !is_string($choice['ministryName'])) {
            throw new InvalidArgumentException(gettext('Give the new ministry a name of up to 100 characters.'));
        }
        if ($ministryId === 0 && !isset($choice['ministryName'])) {
            $ministryId = self::previousMinistryId($stored);
        }
        if ($ministryId > 0) {
            $target = VolunteerMinistryQuery::create()->findPk($ministryId);
            if ($target === null) {
                throw new InvalidArgumentException(gettext('That ministry does not exist.'));
            }

            return [$target, (string) $target->getName(), $ownMinistryIds];
        }

        $name = trim($choice['ministryName'] ?? gettext('Volunteer Opportunities'));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException(gettext('Give the new ministry a name of up to 100 characters.'));
        }
        if (VolunteerMinistryQuery::create()->filterByName($name)->exists()) {
            throw new InvalidArgumentException(sprintf(gettext('A ministry called "%s" already exists. Choose it from the list, or pick another name.'), $name));
        }

        return [null, $name, $ownMinistryIds];
    }

    /** A whole number sent as JSON or in a query string; null for anything else. */
    private static function wholeNumber(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        return is_string($value) && preg_match('/^\s*\d{1,9}\s*$/', $value) === 1 ? (int) $value : null;
    }

    /**
     * For each opportunity: the people linked to it, once each, and the links that cannot convert.
     *
     * @param int[] $opportunityIds
     *
     * @return array<int, array{0: array<int, array{id: int, name: string}>, 1: array<int, array{personId: int, reason: string, name: string}>}>
     */
    private function linkedPeople(array $opportunityIds): array
    {
        $links = [];
        if ($opportunityIds !== []) {
            foreach (PersonVolunteerOpportunityQuery::create()
                ->filterByVolunteerOpportunityId($opportunityIds, Criteria::IN)
                ->select(['VolunteerOpportunityId', 'PersonId'])
                ->orderByPersonId()
                ->find() as $link) {
                $links[(int) $link['VolunteerOpportunityId']][(int) $link['PersonId']] = true;
            }
        }

        $found = [];
        $personIds = array_keys(array_replace([], ...array_values($links)));
        if ($personIds !== []) {
            foreach (PersonQuery::create()->filterById($personIds, Criteria::IN)->orderByLastName()->orderByFirstName()->orderById()->find() as $person) {
                $found[(int) $person->getId()] = $person;
            }
        }

        $result = [];
        foreach ($opportunityIds as $opportunityId) {
            $linked = $links[$opportunityId] ?? [];
            $people = [];
            $skipped = [];
            foreach (array_intersect_key($found, $linked) as $id => $person) {
                if ($person->isDeceased()) {
                    $skipped[] = ['personId' => $id, 'reason' => self::SKIP_DECEASED, 'name' => $person->getFullName()];
                } else {
                    $people[] = ['id' => $id, 'name' => $person->getFullName()];
                }
            }
            foreach (array_keys(array_diff_key($linked, $found)) as $id) {
                $skipped[] = ['personId' => $id, 'reason' => self::SKIP_MISSING, 'name' => ''];
            }
            $result[$opportunityId] = [$people, $skipped];
        }

        return $result;
    }

    /**
     * The people linked to a converted opportunity who are not among `$qualified`, its
     * position's qualified people. Nobody when the position is gone or inactive (null).
     *
     * @param array<int, array{id: int, name: string}> $people
     * @param array<int, true>|null                    $qualified
     *
     * @return array<int, array{id: int, name: string}>
     */
    private static function pendingPeople(array $people, ?array $qualified): array
    {
        if ($qualified === null) {
            return [];
        }

        return array_values(array_filter($people, static fn (array $person): bool => !isset($qualified[$person['id']])));
    }

    /**
     * Each existing, active position => the people with a qualification for it, active or
     * removed. A person linked to its opportunity and missing here was linked since the
     * conversion; one whose qualification was removed in Ministries stays removed.
     *
     * @param int[] $positionIds
     *
     * @return array<int, array<int, true>>
     */
    private function qualificationsOf(array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }
        $qualified = [];
        foreach (VolunteerPositionQuery::create()->filterById($positionIds, Criteria::IN)->filterByActive(true)->select(['Id'])->find() as $positionId) {
            $qualified[(int) $positionId] = [];
        }
        if ($qualified === []) {
            return [];
        }
        foreach (VolunteerQualificationQuery::create()
            ->filterByPositionId(array_keys($qualified), Criteria::IN)
            ->select(['PositionId', 'PersonId'])
            ->find() as $qualification) {
            $qualified[(int) $qualification['PositionId']][(int) $qualification['PersonId']] = true;
        }

        return $qualified;
    }

    private function baseName(VolunteerOpportunity $opportunity): string
    {
        $name = trim((string) $opportunity->getName());

        return $name !== '' ? $name : sprintf(gettext('Opportunity #%d'), (int) $opportunity->getId());
    }

    /**
     * `$name`, or `$name (2)`, `$name (3)`… — the first not in `$taken`, which it joins.
     *
     * @param array<string, true> $taken lower-cased names
     */
    private function freeName(string $name, array &$taken): string
    {
        $candidate = $name;
        for ($n = 2; isset($taken[mb_strtolower($candidate)]); ++$n) {
            $candidate = sprintf('%s (%d)', $name, $n);
        }
        $taken[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    /** @return array<string, true> */
    private function ministryNames(): array
    {
        $names = [];
        foreach (VolunteerMinistryQuery::create()->select(['Name'])->find() as $name) {
            $names[mb_strtolower((string) $name)] = true;
        }

        return $names;
    }

    /** @return array<string, true> */
    private function positionNamesOf(VolunteerTeam $team): array
    {
        $names = [];
        foreach (VolunteerPositionQuery::create()->filterByTeamId($team->getId())->select(['Name'])->find() as $name) {
            $names[mb_strtolower((string) $name)] = true;
        }

        return $names;
    }

    private function firstTeam(VolunteerMinistry $ministry): VolunteerTeam
    {
        $team = VolunteerTeamQuery::create()->filterByMinistryId($ministry->getId())->orderById()->findOne();
        if ($team === null) {
            throw new InvalidArgumentException(sprintf(gettext('The ministry "%s" has no team to hold the positions.'), $ministry->getName()));
        }

        return $team;
    }

    /** @return array{0: VolunteerMinistry, 1: VolunteerTeam} */
    private function ownMinistry(VolunteerMinistry $ministry): array
    {
        return [$ministry, $this->firstTeam($ministry)];
    }
}
