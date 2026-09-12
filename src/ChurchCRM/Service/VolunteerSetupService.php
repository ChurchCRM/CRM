<?php

namespace ChurchCRM\Service;

use ChurchCRM\Exceptions\VolunteerSetupException;
use ChurchCRM\model\ChurchCRM\Map\VolunteerMinistryTableMap;
use ChurchCRM\model\ChurchCRM\Map\VolunteerTeamTableMap;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerRequirementQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScope;
use ChurchCRM\model\ChurchCRM\VolunteerScopeQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;

/**
 * Volunteer Management v2 — the setup half of the domain: ministries, teams and
 * positions (#9715, epic #9701, design §2.3, §2.4, §2.6, §3.4).
 *
 * Every mutating method takes the acting `User` and asks
 * `VolunteerAuthorizationService` before it touches a row, so the API layer can
 * never be the only thing standing between a caller and a write — design §4.5
 * layer three. The role middleware answers "may this caller coordinate anything
 * at all", the entity middleware answers "may they touch THIS record", and this
 * class answers "is this particular operation on this record allowed", which is
 * the only layer that can see the payload (a team id from another ministry, a
 * ministry-wide position a team leader may not create).
 *
 * Two rules run through the whole file and are worth stating once:
 *
 *   **Deactivate, never delete, once referenced.** A position with
 *   qualifications, requirements or assignments is refused with 409 and a count
 *   (§2.6); a ministry with occurrences or assignments likewise (§3.3.1). Both
 *   mirror `DELETE /api/volunteer-opportunities/{id}`
 *   (`src/api/routes/system/volunteer-opportunities.php`), which deliberately
 *   diverges from V1's cascading editor. History is not a coordinator's to
 *   destroy by accident.
 *
 *   **Names are checked in PHP, not left to the index.** `vmin_name_uidx` and
 *   `vtem_ministry_name_uidx` would raise a driver error, not a 409, and
 *   `vpos_ministry_team_name_uidx` does not fire at all for two ministry-wide
 *   positions because MySQL treats NULLs in a UNIQUE index as distinct (§2.6).
 *   So each create/update does its own case-insensitive check and throws a
 *   conflict the route renders as 409.
 *
 * #9707 adds the pool and qualification halves to this same class. The sections
 * below are separated by banner comments so that lands as an addition rather
 * than a merge.
 */
class VolunteerSetupService
{
    private LoggerInterface $logger;

    private VolunteerAuthorizationService $authz;

    public function __construct(?VolunteerAuthorizationService $authz = null)
    {
        $this->logger = LoggerUtils::getAppLogger();
        // Injectable so one request's scope cache is shared with the middlewares
        // that already resolved the same entities (§4.4); defaults to its own.
        $this->authz = $authz ?? new VolunteerAuthorizationService();
    }

    public function getAuthorizationService(): VolunteerAuthorizationService
    {
        return $this->authz;
    }

    // ══ Ministries ════════════════════════════════════════════════════════

    /**
     * Create a ministry. **Manager-only** (§4.6): a ministry coordinator may run
     * the ministry they were given, never mint themselves another one.
     *
     * @throws VolunteerSetupException 403 when the actor is not a global manager,
     *                                 400 on an empty name, 409 on a duplicate
     */
    public function createMinistry(string $name, ?string $description, User $actor): VolunteerMinistry
    {
        if (!$this->authz->isGlobalManager($actor)) {
            throw VolunteerSetupException::forbidden(gettext('Creating a ministry requires volunteer manager access'));
        }

        $name = $this->requireName($name, gettext('A ministry name is required'));
        $this->assertMinistryNameFree($name, null);

        $ministry = new VolunteerMinistry();
        $ministry->setName($name);
        $ministry->setDescription($this->normalizeDescription($description));
        $ministry->setActive(true);
        // Naive wall-clock in sTimeZone, like every other V2 timestamp (§2.0).
        $ministry->setCreatedDate(DateTimeUtils::getNowDateTime());
        $ministry->setCreatedByPersonId((int) $actor->getId());
        $ministry->save();

        $this->logger->info('Volunteer ministry created', [
            'ministryId' => $ministry->getId(),
            'name' => $ministry->getName(),
            'actor' => $actor->getId(),
        ]);

        return $ministry;
    }

    /**
     * Update a ministry's name, description or active flag. Coordinator scope
     * (§4.6) — the entity middleware has usually decided this already, but the
     * service repeats it so a non-HTTP caller cannot skip it.
     *
     * Only keys actually present in $fields are written, so a partial payload
     * never silently blanks a column.
     *
     * @param array{name?: string, description?: string|null, active?: bool} $fields
     *
     * @throws VolunteerSetupException
     */
    public function updateMinistry(VolunteerMinistry $ministry, array $fields, User $actor): VolunteerMinistry
    {
        $this->assertCanManageMinistry($actor, (int) $ministry->getId());

        if (array_key_exists('name', $fields)) {
            $name = $this->requireName((string) $fields['name'], gettext('A ministry name is required'));
            $this->assertMinistryNameFree($name, (int) $ministry->getId());
            $ministry->setName($name);
        }

        if (array_key_exists('description', $fields)) {
            $ministry->setDescription($this->normalizeDescription($fields['description']));
        }

        if (array_key_exists('active', $fields)) {
            $ministry->setActive((bool) $fields['active']);
        }

        $ministry->save();

        $this->logger->info('Volunteer ministry updated', [
            'ministryId' => $ministry->getId(),
            'actor' => $actor->getId(),
        ]);

        return $ministry;
    }

    /**
     * Delete a ministry. **Manager-only** (§4.6), and refused with 409 the moment
     * any occurrence or assignment hangs off it — at that point there is service
     * history to protect and `Active = 0` is the right answer (§2.3).
     *
     * When it does go through, the foreign keys take teams, pools, positions,
     * qualifications, schedules and requirements with it. `volunteer_scope_vscp`
     * does not: its target column is polymorphic and carries no foreign key
     * (§2.15), so those rows are removed explicitly, inside the same transaction,
     * or the installation is left with grants pointing at nothing.
     *
     * @throws VolunteerSetupException
     */
    public function deleteMinistry(VolunteerMinistry $ministry, User $actor): void
    {
        if (!$this->authz->isGlobalManager($actor)) {
            throw VolunteerSetupException::forbidden(gettext('Deleting a ministry requires volunteer manager access'));
        }

        $ministryId = (int) $ministry->getId();
        $occurrenceCount = $this->countMinistryOccurrences($ministryId);
        $assignmentCount = $this->countMinistryAssignments($ministryId);

        if ($occurrenceCount > 0 || $assignmentCount > 0) {
            throw VolunteerSetupException::conflict(sprintf(
                gettext('This ministry still has %1$d scheduled occurrences and %2$d assignments. Deactivate it instead of deleting it.'),
                $occurrenceCount,
                $assignmentCount
            ));
        }

        $teamIds = $this->getTeamIds($ministryId);

        $connection = Propel::getWriteConnection(VolunteerMinistryTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $scopeQuery = VolunteerScopeQuery::create()
                ->filterByScopeType(VolunteerScope::TYPE_MINISTRY)
                ->filterByScopeId($ministryId);
            $removedScopes = $scopeQuery->delete($connection);

            if ($teamIds !== []) {
                $removedScopes += VolunteerScopeQuery::create()
                    ->filterByScopeType(VolunteerScope::TYPE_TEAM)
                    ->filterByScopeId($teamIds, Criteria::IN)
                    ->delete($connection);
            }

            $ministry->delete($connection);
            $connection->commit();

            $this->logger->info('Volunteer ministry deleted', [
                'ministryId' => $ministryId,
                'removedScopeRows' => $removedScopes,
                'actor' => $actor->getId(),
            ]);
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    /**
     * Ministries the caller may administer, as a QUERY-level filter rather than a
     * post-hydration sieve (§4.4). A global manager sees everything; a coordinator
     * sees their grants; anyone else sees nothing, and the empty allow-list is
     * short-circuited rather than handed to `filterById([])`.
     *
     * @return VolunteerMinistry[]
     */
    public function listMinistriesFor(User $actor, ?bool $activeOnly = null): array
    {
        $query = VolunteerMinistryQuery::create();

        if (!$this->authz->isGlobalManager($actor)) {
            $ministryIds = $this->authz->getManagedMinistryIds($actor);
            if ($ministryIds === []) {
                return [];
            }
            $query->filterById($ministryIds, Criteria::IN);
        }

        if ($activeOnly !== null) {
            $query->filterByActive($activeOnly);
        }

        return iterator_to_array($query->orderByName()->find(), false);
    }

    // ══ Teams ═════════════════════════════════════════════════════════════

    /**
     * Create a team under a ministry. Ministry-coordinator scope: a team leader
     * may not create teams (§4.6).
     *
     * @throws VolunteerSetupException
     */
    public function createTeam(VolunteerMinistry $ministry, string $name, ?string $description, User $actor): VolunteerTeam
    {
        $this->assertCanManageMinistry($actor, (int) $ministry->getId());

        $name = $this->requireName($name, gettext('A team name is required'));
        $this->assertTeamNameFree((int) $ministry->getId(), $name, null);

        $team = new VolunteerTeam();
        $team->setMinistryId((int) $ministry->getId());
        $team->setName($name);
        $team->setDescription($this->normalizeDescription($description));
        $team->setActive(true);
        $team->save();

        $this->logger->info('Volunteer team created', [
            'teamId' => $team->getId(),
            'ministryId' => $ministry->getId(),
            'actor' => $actor->getId(),
        ]);

        return $team;
    }

    /**
     * @param array{name?: string, description?: string|null, active?: bool} $fields
     *
     * @throws VolunteerSetupException
     */
    public function updateTeam(VolunteerTeam $team, array $fields, User $actor): VolunteerTeam
    {
        // The parent ministry's coordinator, not the team leader: renaming a team
        // is a ministry-structure change (§4.6 "Create / edit team").
        $this->assertCanManageMinistry($actor, (int) $team->getMinistryId());

        if (array_key_exists('name', $fields)) {
            $name = $this->requireName((string) $fields['name'], gettext('A team name is required'));
            $this->assertTeamNameFree((int) $team->getMinistryId(), $name, (int) $team->getId());
            $team->setName($name);
        }

        if (array_key_exists('description', $fields)) {
            $team->setDescription($this->normalizeDescription($fields['description']));
        }

        if (array_key_exists('active', $fields)) {
            $team->setActive((bool) $fields['active']);
        }

        $team->save();

        $this->logger->info('Volunteer team updated', [
            'teamId' => $team->getId(),
            'actor' => $actor->getId(),
        ]);

        return $team;
    }

    /**
     * Delete a team, but only while nothing hangs off it.
     *
     * The design spells out the 409 rule for positions (§2.6) and for ministries
     * (§3.3.1) and is silent about teams — but `vpos_vtem_ID` is `ON DELETE SET
     * NULL`, so deleting a team with positions would silently promote every one of
     * them to ministry-wide, which is a data change nobody asked for and which
     * cannot be undone from the UI. So a team that still owns positions or
     * schedules is refused with the same shape of message; empty teams delete.
     *
     * @throws VolunteerSetupException
     */
    public function deleteTeam(VolunteerTeam $team, User $actor): void
    {
        $this->assertCanManageMinistry($actor, (int) $team->getMinistryId());

        $teamId = (int) $team->getId();
        $positionCount = VolunteerPositionQuery::create()->filterByTeamId($teamId)->count();
        $scheduleCount = VolunteerScheduleQuery::create()->filterByTeamId($teamId)->count();

        if ($positionCount > 0 || $scheduleCount > 0) {
            throw VolunteerSetupException::conflict(sprintf(
                gettext('This team still has %1$d positions and %2$d schedules. Move or remove them first, or deactivate the team.'),
                $positionCount,
                $scheduleCount
            ));
        }

        $connection = Propel::getWriteConnection(VolunteerTeamTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            VolunteerScopeQuery::create()
                ->filterByScopeType(VolunteerScope::TYPE_TEAM)
                ->filterByScopeId($teamId)
                ->delete($connection);
            $team->delete($connection);
            $connection->commit();

            $this->logger->info('Volunteer team deleted', [
                'teamId' => $teamId,
                'actor' => $actor->getId(),
            ]);
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    /**
     * @return VolunteerTeam[]
     */
    public function listTeams(int $ministryId, ?bool $activeOnly = null): array
    {
        $query = VolunteerTeamQuery::create()->filterByMinistryId($ministryId);

        if ($activeOnly !== null) {
            $query->filterByActive($activeOnly);
        }

        return iterator_to_array($query->orderByName()->find(), false);
    }

    // ══ Positions ═════════════════════════════════════════════════════════

    /**
     * Create a position, ministry-wide when `$team` is null and team-scoped
     * otherwise.
     *
     * Authorization follows the ownership of the row being created, which is
     * exactly what `canManagePosition()` already decides for an existing row: a
     * team-scoped position belongs to its team, so a team leader may create one
     * in their own team; a ministry-wide position belongs to the ministry, so
     * only its coordinator may (§4.6).
     *
     * @throws VolunteerSetupException
     */
    public function createPosition(
        VolunteerMinistry $ministry,
        ?VolunteerTeam $team,
        string $name,
        ?string $description,
        int $order,
        User $actor
    ): VolunteerPosition {
        $ministryId = (int) $ministry->getId();

        if ($team !== null) {
            if ((int) $team->getMinistryId() !== $ministryId) {
                throw VolunteerSetupException::invalid(gettext('That team belongs to a different ministry'));
            }
            $this->assertCanManageTeam($actor, (int) $team->getId());
        } else {
            $this->assertCanManageMinistry($actor, $ministryId);
        }

        $name = $this->requireName($name, gettext('A position name is required'));
        $teamId = $team === null ? null : (int) $team->getId();
        $this->assertPositionNameFree($ministryId, $teamId, $name, null);

        $position = new VolunteerPosition();
        $position->setMinistryId($ministryId);
        $position->setTeamId($teamId);
        $position->setName($name);
        $position->setDescription($this->normalizeDescription($description));
        $position->setActive(true);
        $position->setOrder($order);
        $position->save();

        $this->logger->info('Volunteer position created', [
            'positionId' => $position->getId(),
            'ministryId' => $ministryId,
            'teamId' => $teamId,
            'actor' => $actor->getId(),
        ]);

        return $position;
    }

    /**
     * Update a position's name, description, team scope, display order or active
     * flag.
     *
     * Moving a position between team scopes is authorized against **both** ends:
     * the caller must be allowed to touch the position where it is now (that is
     * the entity check) and to own it where it is going. Without the second half
     * a team leader could push their team's position out to ministry-wide.
     *
     * @param array{name?: string, description?: string|null, teamId?: int|null, order?: int, active?: bool} $fields
     *
     * @throws VolunteerSetupException
     */
    public function updatePosition(VolunteerPosition $position, array $fields, User $actor): VolunteerPosition
    {
        $this->assertCanManagePosition($actor, (int) $position->getId());

        $ministryId = (int) $position->getMinistryId();
        $targetTeamId = $position->getTeamId() === null ? null : (int) $position->getTeamId();

        if (array_key_exists('teamId', $fields)) {
            $targetTeamId = $fields['teamId'] === null || $fields['teamId'] === '' ? null : (int) $fields['teamId'];

            if ($targetTeamId !== null) {
                $team = VolunteerTeamQuery::create()->findPk($targetTeamId);
                if ($team === null || (int) $team->getMinistryId() !== $ministryId) {
                    throw VolunteerSetupException::invalid(gettext('That team belongs to a different ministry'));
                }
                $this->assertCanManageTeam($actor, $targetTeamId);
            } else {
                $this->assertCanManageMinistry($actor, $ministryId);
            }

            $position->setTeamId($targetTeamId);
        }

        if (array_key_exists('name', $fields)) {
            $name = $this->requireName((string) $fields['name'], gettext('A position name is required'));
            $this->assertPositionNameFree($ministryId, $targetTeamId, $name, (int) $position->getId());
            $position->setName($name);
        } elseif (array_key_exists('teamId', $fields)) {
            // The scope moved; the existing name has to be free in the new scope too.
            $this->assertPositionNameFree($ministryId, $targetTeamId, (string) $position->getName(), (int) $position->getId());
        }

        if (array_key_exists('description', $fields)) {
            $position->setDescription($this->normalizeDescription($fields['description']));
        }

        if (array_key_exists('order', $fields)) {
            $position->setOrder((int) $fields['order']);
        }

        if (array_key_exists('active', $fields)) {
            $position->setActive((bool) $fields['active']);
        }

        $position->save();

        $this->logger->info('Volunteer position updated', [
            'positionId' => $position->getId(),
            'actor' => $actor->getId(),
        ]);

        return $position;
    }

    /**
     * The #9715 acceptance criterion "position activation/deactivation does not
     * destroy historical assignments", as one call. Deliberately separate from
     * `updatePosition()` so the UI's toggle and the API's `active` field cannot
     * drift apart.
     *
     * @throws VolunteerSetupException
     */
    public function setPositionActive(VolunteerPosition $position, bool $active, User $actor): VolunteerPosition
    {
        $this->assertCanManagePosition($actor, (int) $position->getId());

        $position->setActive($active);
        $position->save();

        $this->logger->info('Volunteer position activation changed', [
            'positionId' => $position->getId(),
            'active' => $active,
            'actor' => $actor->getId(),
        ]);

        return $position;
    }

    /**
     * Delete a position, but only while nothing references it: qualifications,
     * staffing requirements or assignments all make it history (§2.6). The 409
     * message names the counts so the coordinator knows why, and deactivation is
     * the documented alternative.
     *
     * @throws VolunteerSetupException
     */
    public function deletePosition(VolunteerPosition $position, User $actor): void
    {
        $this->assertCanManagePosition($actor, (int) $position->getId());

        $positionId = (int) $position->getId();
        $qualificationCount = VolunteerQualificationQuery::create()->filterByPositionId($positionId)->count();
        $requirementCount = VolunteerRequirementQuery::create()->filterByPositionId($positionId)->count();
        $assignmentCount = VolunteerAssignmentQuery::create()->filterByPositionId($positionId)->count();

        if ($qualificationCount > 0 || $requirementCount > 0 || $assignmentCount > 0) {
            throw VolunteerSetupException::conflict(sprintf(
                gettext('This position is referenced by %1$d qualifications, %2$d staffing requirements and %3$d assignments. Deactivate it instead of deleting it.'),
                $qualificationCount,
                $requirementCount,
                $assignmentCount
            ));
        }

        $position->delete();

        $this->logger->info('Volunteer position deleted', [
            'positionId' => $positionId,
            'actor' => $actor->getId(),
        ]);
    }

    /**
     * Positions of a ministry, optionally narrowed to one team and/or to the
     * active ones, ordered the way the coordinator arranged them.
     *
     * `$visibleTeamIds` is the read-scoping hook for a team leader (§4.4): pass
     * the ids they manage and the filter happens in the query. Passing `null`
     * means "no narrowing", which is what a ministry coordinator gets.
     *
     * @param int[]|null $visibleTeamIds
     *
     * @return VolunteerPosition[]
     */
    public function listPositions(
        int $ministryId,
        ?int $teamId = null,
        ?bool $activeOnly = null,
        ?array $visibleTeamIds = null
    ): array {
        $query = VolunteerPositionQuery::create()->filterByMinistryId($ministryId);

        if ($teamId !== null) {
            $query->filterByTeamId($teamId);
        }

        if ($activeOnly !== null) {
            $query->filterByActive($activeOnly);
        }

        if ($visibleTeamIds !== null) {
            if ($visibleTeamIds === []) {
                return [];
            }
            // A team leader sees their teams' positions, never the ministry-wide
            // ones — those belong to the coordinator (§4.4).
            $query->filterByTeamId($visibleTeamIds, Criteria::IN);
        }

        return iterator_to_array($query->orderByOrder()->orderByName()->find(), false);
    }

    // ══ Counts for list rendering ═════════════════════════════════════════

    /**
     * Team counts for a set of ministries, in ONE query rather than one per row.
     *
     * A ministry list is the place an N+1 would hide most comfortably — it looks
     * harmless at three ministries and is not at thirty. Only the foreign key
     * column is hydrated; the tallying is a loop over ints.
     *
     * @param int[] $ministryIds
     *
     * @return array<int, int> ministry id → count, zero-filled for every id asked about
     */
    public function countTeamsByMinistry(array $ministryIds): array
    {
        return $this->tally(
            $ministryIds,
            VolunteerTeamQuery::create()
        );
    }

    /**
     * @param int[] $ministryIds
     *
     * @return array<int, int>
     */
    public function countPositionsByMinistry(array $ministryIds): array
    {
        return $this->tally(
            $ministryIds,
            VolunteerPositionQuery::create()
        );
    }

    /**
     * Team counts for a set of teams, keyed by team id. Used by the ministry
     * detail page so each team row can show how many positions it owns.
     *
     * @param int[] $teamIds
     *
     * @return array<int, int>
     */
    public function countPositionsByTeam(array $teamIds): array
    {
        $counts = array_fill_keys(array_map('intval', $teamIds), 0);
        if ($teamIds === []) {
            return $counts;
        }

        $rows = VolunteerPositionQuery::create()
            ->filterByTeamId($teamIds, Criteria::IN)
            ->select(['TeamId'])
            ->find();

        foreach ($rows as $teamId) {
            $key = (int) $teamId;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Shared body of the two counters above: hydrate only the `MinistryId`
     * column for every child row of the given ministries and tally in PHP.
     *
     * @param int[]        $ministryIds
     * @param ModelCriteria $query a query whose model has a MinistryId column
     *
     * @return array<int, int>
     */
    private function tally(array $ministryIds, ModelCriteria $query): array
    {
        $counts = array_fill_keys(array_map('intval', $ministryIds), 0);
        if ($ministryIds === []) {
            return $counts;
        }

        $rows = $query
            ->filterByMinistryId($ministryIds, Criteria::IN)
            ->select(['MinistryId'])
            ->find();

        foreach ($rows as $ministryId) {
            $key = (int) $ministryId;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    // ══ Internals ═════════════════════════════════════════════════════════

    /** @throws VolunteerSetupException */
    private function assertCanManageMinistry(User $actor, int $ministryId): void
    {
        if (!$this->authz->canManageMinistry($actor, $ministryId)) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this ministry'));
        }
    }

    /** @throws VolunteerSetupException */
    private function assertCanManageTeam(User $actor, int $teamId): void
    {
        if (!$this->authz->canManageTeam($actor, $teamId)) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this team'));
        }
    }

    /** @throws VolunteerSetupException */
    private function assertCanManagePosition(User $actor, int $positionId): void
    {
        if (!$this->authz->canManagePosition($actor, $positionId)) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this position'));
        }
    }

    /** @throws VolunteerSetupException */
    private function requireName(string $name, string $message): string
    {
        $name = trim($name);
        if ($name === '') {
            throw VolunteerSetupException::invalid($message);
        }

        return $name;
    }

    /** An absent or blank description is stored as NULL, never as ''. */
    private function normalizeDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $description = trim($description);

        return $description === '' ? null : $description;
    }

    /**
     * `vmin_name_uidx` would raise a driver error rather than a 409, so the check
     * happens here. `Criteria::NOT_EQUAL` on the id is what makes a no-op rename
     * (saving a record under its own name) succeed.
     *
     * @throws VolunteerSetupException
     */
    private function assertMinistryNameFree(string $name, ?int $exceptMinistryId): void
    {
        $query = VolunteerMinistryQuery::create()->filterByName($name);

        if ($exceptMinistryId !== null) {
            $query->filterById($exceptMinistryId, Criteria::NOT_EQUAL);
        }

        if ($query->count() > 0) {
            throw VolunteerSetupException::conflict(gettext('A ministry with that name already exists'));
        }
    }

    /** @throws VolunteerSetupException */
    private function assertTeamNameFree(int $ministryId, string $name, ?int $exceptTeamId): void
    {
        $query = VolunteerTeamQuery::create()
            ->filterByMinistryId($ministryId)
            ->filterByName($name);

        if ($exceptTeamId !== null) {
            $query->filterById($exceptTeamId, Criteria::NOT_EQUAL);
        }

        if ($query->count() > 0) {
            throw VolunteerSetupException::conflict(gettext('A team with that name already exists in this ministry'));
        }
    }

    /**
     * The check §2.6 explicitly calls for. `vpos_ministry_team_name_uidx` covers
     * team-scoped positions only: with `vpos_vtem_ID IS NULL` on both rows MySQL
     * considers the keys distinct and lets a second ministry-wide "Espresso"
     * through. Matching the null scope with `filterByTeamId(null)` — which Propel
     * renders as `IS NULL` — closes that hole without the `NOT NULL DEFAULT 0`
     * sentinel the design forbids.
     *
     * @throws VolunteerSetupException
     */
    private function assertPositionNameFree(int $ministryId, ?int $teamId, string $name, ?int $exceptPositionId): void
    {
        $query = VolunteerPositionQuery::create()
            ->filterByMinistryId($ministryId)
            ->filterByTeamId($teamId)
            ->filterByName($name);

        if ($exceptPositionId !== null) {
            $query->filterById($exceptPositionId, Criteria::NOT_EQUAL);
        }

        if ($query->count() > 0) {
            throw VolunteerSetupException::conflict(gettext('A position with that name already exists in this ministry'));
        }
    }

    /**
     * @return int[]
     */
    private function getTeamIds(int $ministryId): array
    {
        $ids = [];
        foreach (VolunteerTeamQuery::create()->filterByMinistryId($ministryId)->select(['Id'])->find() as $id) {
            $ids[] = (int) $id;
        }

        return $ids;
    }

    private function countMinistryOccurrences(int $ministryId): int
    {
        return VolunteerOccurrenceQuery::create()
            ->useScheduleQuery()
                ->filterByMinistryId($ministryId)
            ->endUse()
            ->count();
    }

    private function countMinistryAssignments(int $ministryId): int
    {
        return VolunteerAssignmentQuery::create()
            ->usePositionQuery()
                ->filterByMinistryId($ministryId)
            ->endUse()
            ->count();
    }
}
