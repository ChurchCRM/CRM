<?php

namespace ChurchCRM\Service;

use ChurchCRM\Exceptions\VolunteerSetupException;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\VolunteerMinistryTableMap;
use ChurchCRM\model\ChurchCRM\Map\VolunteerTeamTableMap;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPool;
use ChurchCRM\model\ChurchCRM\VolunteerPoolQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualification;
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
 * Volunteer Management v2 — the setup half of the domain: ministries, teams,
 * positions (#9715) and, since #9707, volunteer pools and qualifications
 * (epic #9701, design §2.3, §2.4, §2.5, §2.6, §2.7, §3.4).
 *
 * Every mutating method takes the acting `User` and asks
 * `VolunteerAuthorizationService` before it touches a row, so the API layer can
 * never be the only thing standing between a caller and a write — design §4.5
 * layer three. The role middleware answers "may this caller coordinate anything
 * at all", the entity middleware answers "may they touch THIS record", and this
 * class answers "is this particular operation on this record allowed", which is
 * the only layer that can see the payload (a team id from another ministry, a
 * team another leader owns).
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
 *   **Names are checked in PHP, not left to the index.** `vmin_name_uidx`,
 *   `vtem_ministry_name_uidx` and `vpos_ministry_team_name_uidx` would each raise
 *   a driver error rather than a 409, and none of them is case-insensitive by
 *   design — only by collation. So each create/update does its own
 *   case-insensitive check and throws a conflict the route renders as 409.
 *
 * #9707 added the pool and qualification halves to this same class rather than
 * a parallel service: they share the authorization helpers, the exception type
 * and the "names are checked in PHP" discipline above, and a coordinator's
 * setup screen calls both halves in one request. The sections are separated by
 * banner comments, so each half is still readable on its own.
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
     * Create a ministry **and its first team**. **Manager-only** (§4.6): a ministry
     * coordinator may run the ministry they were given, never mint themselves
     * another one.
     *
     * D18: a ministry is never team-less. The first team is created here, in the
     * same transaction, so that every caller gets it — the API, the setup wizard
     * and any future importer alike — and so that positions and schedules, which
     * are now `NOT NULL` on their team column, always have somewhere to go. The
     * team is an ordinary row: it can be renamed, and more can be added beside it.
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

        $connection = Propel::getWriteConnection(VolunteerMinistryTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $ministry->save($connection);

            $team = new VolunteerTeam();
            $team->setMinistryId((int) $ministry->getId());
            $team->setName($this->defaultTeamName($name));
            $team->setActive(true);
            $team->save($connection);

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        $this->logger->info('Volunteer ministry created', [
            'ministryId' => $ministry->getId(),
            'name' => $ministry->getName(),
            'defaultTeamId' => $team->getId(),
            'actor' => $actor->getId(),
        ]);

        return $ministry;
    }

    /**
     * The name a ministry's first team is born with — "Coffee Bar" → "Coffee Bar
     * Team" (D18).
     *
     * `vtem_Name` is `VARCHAR(100)` and so is `vmin_Name`, so the suffix can push a
     * maximum-length ministry name over the column; the ministry name is trimmed
     * rather than the suffix dropped, because the word "Team" is what makes the
     * label read as one. Uniqueness inside the ministry is free: the ministry has
     * no other team yet.
     */
    private function defaultTeamName(string $ministryName): string
    {
        $suffix = ' ' . gettext('Team');
        $room = 100 - mb_strlen($suffix);

        return rtrim(mb_substr($ministryName, 0, $room)) . $suffix;
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
     * Delete a team, but only while nothing hangs off it **and it is not the last
     * team its ministry has**.
     *
     * D18: a ministry always has at least one team, so the last one cannot be
     * deleted — the 409 says to rename it instead, because renaming is what a
     * coordinator who wants "a different team" actually wants, and the only other
     * way out would be to leave the ministry in a state positions and schedules
     * cannot be created in at all.
     *
     * A team that still owns positions or schedules is refused too: `vpos_vtem_ID`
     * and `vsch_vtem_ID` are `ON DELETE CASCADE`, so deleting such a team would
     * silently take real setup — and, through the schedule, real occurrences —
     * with it. Empty, non-last teams delete.
     *
     * @throws VolunteerSetupException
     */
    public function deleteTeam(VolunteerTeam $team, User $actor): void
    {
        $this->assertCanManageMinistry($actor, (int) $team->getMinistryId());

        $teamId = (int) $team->getId();

        $siblingCount = VolunteerTeamQuery::create()
            ->filterByMinistryId((int) $team->getMinistryId())
            ->filterById($teamId, Criteria::NOT_EQUAL)
            ->count();

        if ($siblingCount === 0) {
            throw VolunteerSetupException::conflict(
                gettext('A ministry needs at least one team. Rename it instead.')
            );
        }

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
     * Create a position in one of the ministry's teams.
     *
     * D18: a position ALWAYS belongs to a team, so `$team` is not optional. The
     * "ministry-wide position" that used to exist was the mechanism behind the
     * ambiguity this decision removes — two teams under one ministry could each
     * own a "Lead Teacher", and a ministry-wide list showed the name twice with
     * nothing to tell them apart.
     *
     * Authorization follows the ownership of the row being created, which is
     * exactly what `canManagePosition()` already decides for an existing row: a
     * position belongs to its team, so a team leader may create one in their own
     * team and a ministry coordinator in any of theirs (§4.6).
     *
     * @throws VolunteerSetupException
     */
    public function createPosition(
        VolunteerMinistry $ministry,
        VolunteerTeam $team,
        string $name,
        ?string $description,
        int $order,
        User $actor
    ): VolunteerPosition {
        $ministryId = (int) $ministry->getId();

        if ((int) $team->getMinistryId() !== $ministryId) {
            throw VolunteerSetupException::invalid(gettext('That team belongs to a different ministry'));
        }
        $this->assertCanManageTeam($actor, (int) $team->getId());

        $name = $this->requireName($name, gettext('A position name is required'));
        $teamId = (int) $team->getId();
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
     * Moving a position between teams is authorized against **both** ends: the
     * caller must be allowed to touch the position where it is now (that is the
     * entity check) and to own it where it is going. Without the second half a
     * team leader could push their team's position into somebody else's team.
     *
     * There is no "move it out of every team" any more (D18): a null `teamId` is a
     * 400, not a promotion to ministry-wide.
     *
     * @param array{name?: string, description?: string|null, teamId?: int, order?: int, active?: bool} $fields
     *
     * @throws VolunteerSetupException
     */
    public function updatePosition(VolunteerPosition $position, array $fields, User $actor): VolunteerPosition
    {
        $this->assertCanManagePosition($actor, (int) $position->getId());

        $ministryId = (int) $position->getMinistryId();
        $targetTeamId = (int) $position->getTeamId();

        if (array_key_exists('teamId', $fields)) {
            if ($fields['teamId'] === null || $fields['teamId'] === '') {
                throw VolunteerSetupException::invalid(gettext('A position belongs to a team; choose one'));
            }

            $targetTeamId = (int) $fields['teamId'];
            $team = VolunteerTeamQuery::create()->findPk($targetTeamId);
            if ($team === null || (int) $team->getMinistryId() !== $ministryId) {
                throw VolunteerSetupException::invalid(gettext('That team belongs to a different ministry'));
            }
            $this->assertCanManageTeam($actor, $targetTeamId);

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
            // A team leader sees their own teams' positions and nobody else's
            // (§4.4). Every position has a team now, so there is no third case.
            $query->filterByTeamId($visibleTeamIds, Criteria::IN);
        }

        return iterator_to_array($query->orderByOrder()->orderByName()->find(), false);
    }

    // ══ Pools (#9707) ═════════════════════════════════════════════════════
    //
    // D1: a Group *is* the roster. `volunteer_pool_vpol` is the link and
    // nothing else — it stores no people, and V2 never copies a membership row
    // (§2.5). Everything below therefore reads `person2group2role_p2g2r`
    // live; there is no cache to invalidate and no second source of truth.
    //
    // `vpol_OwnerId` is polymorphic and so carries no foreign key. That makes
    // this class the only thing standing between the table and a row pointing
    // at a ministry or team that does not exist, which is why linkPool() looks
    // the owner up before it writes.

    /**
     * Link an existing Group to a ministry or a team as one of its volunteer
     * pools.
     *
     * Authorization follows the owner (§4.6 "Link / unlink a pool Group"): a
     * ministry-owned pool needs the ministry's coordinator, a team-owned pool
     * the team's leader or the ministry's coordinator above them.
     *
     * @param string $ownerType VolunteerPool::OWNER_TYPE_MINISTRY|OWNER_TYPE_TEAM
     *
     * @throws VolunteerSetupException 400 on an unknown owner type, 403 outside
     *                                 the actor's scope, 404 when the owner or
     *                                 the group does not exist, 409 when the
     *                                 same group is already linked to that owner
     */
    public function linkPool(string $ownerType, int $ownerId, int $groupId, ?string $label, User $actor): VolunteerPool
    {
        if (!in_array($ownerType, VolunteerPool::allOwnerTypes(), true)) {
            throw VolunteerSetupException::invalid(gettext('A pool belongs to a ministry or to a team'));
        }

        // The owner has no foreign key to lean on (§2.5), so its existence is
        // checked here or nowhere.
        if ($ownerType === VolunteerPool::OWNER_TYPE_MINISTRY) {
            if (VolunteerMinistryQuery::create()->findPk($ownerId) === null) {
                throw VolunteerSetupException::notFound(gettext('Ministry not found'));
            }
            $this->assertCanManageMinistry($actor, $ownerId);
        } else {
            if (VolunteerTeamQuery::create()->findPk($ownerId) === null) {
                throw VolunteerSetupException::notFound(gettext('Team not found'));
            }
            $this->assertCanManageTeam($actor, $ownerId);
        }

        // `vpol_grp_ID` does have a foreign key, but a missing group would
        // surface as a driver error rather than a 404, so it is checked too.
        if (GroupQuery::create()->findPk($groupId) === null) {
            throw VolunteerSetupException::notFound(gettext('Group not found'));
        }

        $existing = VolunteerPoolQuery::create()
            ->filterByOwnerType($ownerType)
            ->filterByOwnerId($ownerId)
            ->filterByGroupId($groupId)
            ->findOne();

        if ($existing !== null) {
            throw VolunteerSetupException::conflict(gettext('That group is already linked as a volunteer pool here'));
        }

        $pool = new VolunteerPool();
        $pool->setOwnerType($ownerType);
        $pool->setOwnerId($ownerId);
        $pool->setGroupId($groupId);
        $pool->setLabel($this->normalizeLabel($label));
        $pool->save();

        $this->logger->info('Volunteer pool linked', [
            'poolId' => $pool->getId(),
            'ownerType' => $ownerType,
            'ownerId' => $ownerId,
            'groupId' => $groupId,
            'actor' => $actor->getId(),
        ]);

        return $pool;
    }

    /**
     * Unlink a pool. **The Group is never touched** — not its row, not its
     * membership, not its properties. Unlinking a pool is a V2 bookkeeping
     * change, and the roster it pointed at belongs to Groups (D1, §2.5).
     *
     * @throws VolunteerSetupException
     */
    public function unlinkPool(VolunteerPool $pool, User $actor): void
    {
        $this->assertCanManagePool($actor, $pool);

        $poolId = (int) $pool->getId();
        $pool->delete();

        $this->logger->info('Volunteer pool unlinked', [
            'poolId' => $poolId,
            'actor' => $actor->getId(),
        ]);
    }

    /**
     * Every pool feeding a ministry: its own, plus those of its teams.
     *
     * `$teamId` narrows the team half to one team and keeps the ministry half,
     * because a ministry-wide pool feeds every team under it — the same
     * inheritance `getManagedTeamIds()` applies to scope (§4.4).
     *
     * `$visibleTeamIds` is the read-scoping hook for a team leader: pass the
     * ids they manage and only those teams' pools are returned, alongside the
     * ministry's own.
     *
     * @param int[]|null $visibleTeamIds
     *
     * @return VolunteerPool[]
     */
    public function listPools(int $ministryId, ?int $teamId = null, ?array $visibleTeamIds = null): array
    {
        $teamIds = $this->resolvePoolTeamIds($ministryId, $teamId, $visibleTeamIds);

        $pools = iterator_to_array(
            VolunteerPoolQuery::create()
                ->filterByOwnerType(VolunteerPool::OWNER_TYPE_MINISTRY)
                ->filterByOwnerId($ministryId)
                ->orderById()
                ->find(),
            false
        );

        if ($teamIds !== []) {
            $pools = array_merge($pools, iterator_to_array(
                VolunteerPoolQuery::create()
                    ->filterByOwnerType(VolunteerPool::OWNER_TYPE_TEAM)
                    ->filterByOwnerId($teamIds, Criteria::IN)
                    ->orderById()
                    ->find(),
                false
            ));
        }

        return $pools;
    }

    /**
     * The pools owned by ONE team, and nothing above it.
     *
     * The narrow view a team leader gets at `/teams/{id}/pools`: it answers
     * "which groups did I link", not "which groups feed my team" — the latter
     * is `listPools($ministryId, $teamId)`, which also carries the ministry's
     * own pools.
     *
     * @return VolunteerPool[]
     */
    public function listPoolsForTeam(int $teamId): array
    {
        return iterator_to_array(
            VolunteerPoolQuery::create()
                ->filterByOwnerType(VolunteerPool::OWNER_TYPE_TEAM)
                ->filterByOwnerId($teamId)
                ->orderById()
                ->find(),
            false
        );
    }

    /**
     * The people in the pool: the **union** of the linked groups' memberships,
     * de-duplicated. Nothing is copied and nothing is cached — the group
     * membership table is read every time, so a coordinator adding someone in
     * Groups sees them here on the next page load (D1).
     *
     * @return int[] person ids, ascending
     */
    public function getPoolPersonIds(int $ministryId, ?int $teamId = null): array
    {
        return array_keys($this->getPoolMembership($ministryId, $teamId));
    }

    /**
     * The same union, but keyed so a caller can say *which* pool group each
     * person came from — the matrix shows it, and a person in two pools must
     * still be one row.
     *
     * One query over `person2group2role_p2g2r`, not one per group.
     *
     * @return array<int, int[]> person id → the pool group ids they belong to
     */
    public function getPoolMembership(int $ministryId, ?int $teamId = null, ?array $visibleTeamIds = null): array
    {
        $groupIds = [];
        foreach ($this->listPools($ministryId, $teamId, $visibleTeamIds) as $pool) {
            $groupIds[(int) $pool->getGroupId()] = true;
        }

        if ($groupIds === []) {
            return [];
        }

        $membership = [];
        $rows = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId(array_keys($groupIds), Criteria::IN)
            ->select(['PersonId', 'GroupId'])
            ->find();

        foreach ($rows as $row) {
            $personId = (int) $row['PersonId'];
            $groupId = (int) $row['GroupId'];
            if (!isset($membership[$personId])) {
                $membership[$personId] = [];
            }
            if (!in_array($groupId, $membership[$personId], true)) {
                $membership[$personId][] = $groupId;
            }
        }

        ksort($membership);

        return $membership;
    }

    /**
     * Member counts for a set of pool groups, in ONE query.
     *
     * Deliberately not `GroupQuery`'s `memberCount` virtual column: that comes
     * from a `preSelect()` which also injects a second LEFT JOIN and a
     * `GROUP BY` into every query built from it (F22). Counting the membership
     * table directly is one plain aggregate and says what it does.
     *
     * @param int[] $groupIds
     *
     * @return array<int, int> group id → member count, zero-filled
     */
    public function countGroupMembers(array $groupIds): array
    {
        $counts = array_fill_keys(array_map('intval', $groupIds), 0);
        if ($groupIds === []) {
            return $counts;
        }

        $rows = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($groupIds, Criteria::IN)
            ->select(['GroupId'])
            ->find();

        foreach ($rows as $groupId) {
            $key = (int) $groupId;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /** The ministry a pool ultimately belongs to, resolving a team owner. */
    public function getPoolMinistryId(VolunteerPool $pool): ?int
    {
        if ($pool->getOwnerType() === VolunteerPool::OWNER_TYPE_MINISTRY) {
            return (int) $pool->getOwnerId();
        }

        $team = VolunteerTeamQuery::create()->findPk((int) $pool->getOwnerId());

        return $team === null ? null : (int) $team->getMinistryId();
    }

    // ══ Qualifications (#9707) ════════════════════════════════════════════
    //
    // D2/§2.7: person ↔ position, many-to-many, in its own table. Not a Group
    // Role — `person2group2role_p2g2r` has PK (PersonId, GroupId) and so allows
    // exactly one role per person per group (F19), which would make the
    // multi-position case D16 requires structurally impossible.
    //
    // Two rules are load-bearing and are implemented once, here:
    //
    //   **Revocation is deactivation.** `revokeQualification()` sets
    //   `vqal_Active = 0` and keeps the row, and a re-grant reactivates that
    //   same row rather than inserting a second — which is also what makes
    //   `UNIQUE (vqal_per_ID, vqal_vpos_ID)` survivable. Qualification changes
    //   then affect *future* eligibility without rewriting history, because
    //   `volunteer_assignment_vasg` carries no foreign key to a qualification:
    //   it references the person and the position directly, so a past
    //   assignment stays valid and readable whatever happens here.
    //
    //   **Authorization is per position, not per ministry.** A position belongs
    //   to its team, so its team leader may grant on it and so may the ministry
    //   coordinator above them (§4.6). That is exactly `canManagePosition()`, so
    //   nothing is re-derived.
    //
    // Note what is deliberately NOT enforced: the person does **not** have to
    // be in the pool. §2.5 is explicit that "pool ≠ eligibility" — the pool is
    // the candidate set a coordinator picks from, and §4.6 puts no membership
    // condition on granting. A coordinator may qualify someone outside every
    // linked group, which is why the UI offers a person picker as well as the
    // matrix.

    /**
     * Grant a qualification, or reactivate the one that is already there.
     *
     * Idempotent by `vqal_person_position_uidx`: calling this twice leaves one
     * row. Use `findQualification()` first when the caller needs to answer 201
     * vs 200.
     *
     * @throws VolunteerSetupException 403 outside scope, 404 for an unknown person
     */
    public function grantQualification(
        int $personId,
        VolunteerPosition $position,
        User $actor,
        ?string $notes = null
    ): VolunteerQualification {
        $this->assertCanManagePosition($actor, (int) $position->getId());

        if (PersonQuery::create()->findPk($personId) === null) {
            throw VolunteerSetupException::notFound(gettext('Person not found'));
        }

        $qualification = $this->writeQualification($personId, $position, $actor, $notes);

        $this->logger->info('Volunteer qualification granted', [
            'qualificationId' => $qualification->getId(),
            'personId' => $personId,
            'positionId' => $position->getId(),
            'actor' => $actor->getId(),
        ]);

        return $qualification;
    }

    /**
     * Grant one position to a list of people — the Cart sink (P5/P6).
     *
     * The loop lives here rather than in `Cart`, matching the precedent the
     * design names: the route reads `Cart::getCartPeople()` and hands the ids
     * to the service. Authorization is checked ONCE, before the loop, because
     * every row targets the same position.
     *
     * Ids that name nobody are counted as `skipped` rather than aborting the
     * batch: a cart can outlive a deleted person, and failing the whole grant
     * over one stale id would be worse than reporting it.
     *
     * @param int[] $personIds
     *
     * @return array{granted: int, reactivated: int, existing: int, skipped: int, qualifications: VolunteerQualification[]}
     *
     * @throws VolunteerSetupException
     */
    public function grantQualifications(
        array $personIds,
        VolunteerPosition $position,
        User $actor,
        ?string $notes = null
    ): array {
        $this->assertCanManagePosition($actor, (int) $position->getId());

        $result = ['granted' => 0, 'reactivated' => 0, 'existing' => 0, 'skipped' => 0, 'qualifications' => []];
        $seen = [];

        foreach ($personIds as $rawId) {
            $personId = (int) $rawId;
            if ($personId <= 0 || isset($seen[$personId])) {
                continue;
            }
            $seen[$personId] = true;

            if (PersonQuery::create()->findPk($personId) === null) {
                ++$result['skipped'];
                continue;
            }

            $existing = $this->findQualification($personId, (int) $position->getId());
            if ($existing === null) {
                ++$result['granted'];
            } elseif ($existing->getActive()) {
                ++$result['existing'];
            } else {
                ++$result['reactivated'];
            }

            $result['qualifications'][] = $this->writeQualification($personId, $position, $actor, $notes);
        }

        $this->logger->info('Volunteer qualifications granted in bulk', [
            'positionId' => $position->getId(),
            'granted' => $result['granted'],
            'reactivated' => $result['reactivated'],
            'existing' => $result['existing'],
            'skipped' => $result['skipped'],
            'actor' => $actor->getId(),
        ]);

        return $result;
    }

    /**
     * Revoke a qualification by **deactivating** it (§2.7). The row survives,
     * so the grant history and every assignment that predates the revocation
     * stay readable.
     *
     * @throws VolunteerSetupException
     */
    public function revokeQualification(VolunteerQualification $qualification, User $actor): VolunteerQualification
    {
        $this->assertCanManagePosition($actor, (int) $qualification->getPositionId());

        $qualification->setActive(false);
        $qualification->save();

        $this->logger->info('Volunteer qualification revoked', [
            'qualificationId' => $qualification->getId(),
            'personId' => $qualification->getPersonId(),
            'positionId' => $qualification->getPositionId(),
            'actor' => $actor->getId(),
        ]);

        return $qualification;
    }

    /** The one row for this pair, active or not; null when it was never granted. */
    public function findQualification(int $personId, int $positionId): ?VolunteerQualification
    {
        return VolunteerQualificationQuery::create()
            ->filterByPersonId($personId)
            ->filterByPositionId($positionId)
            ->findOne();
    }

    /**
     * Who is qualified for a position. `$activeOnly = null` includes revoked
     * rows, which is what makes the grant history visible.
     *
     * @return VolunteerQualification[]
     */
    public function listQualifications(int $positionId, ?bool $activeOnly = null): array
    {
        $query = VolunteerQualificationQuery::create()->filterByPositionId($positionId);

        if ($activeOnly !== null) {
            $query->filterByActive($activeOnly);
        }

        return iterator_to_array($query->orderByGrantedDate()->orderById()->find(), false);
    }

    /**
     * One person's qualifications, optionally narrowed to a set of ministries —
     * which is how the read is scoped for a coordinator (§4.4): the filter goes
     * into the query, not into a loop over hydrated rows.
     *
     * @param int[]|null $ministryIds null means "no narrowing" (manager/admin)
     *
     * @return VolunteerQualification[]
     */
    public function listQualificationsForPerson(int $personId, ?array $ministryIds = null): array
    {
        $query = VolunteerQualificationQuery::create()->filterByPersonId($personId);

        if ($ministryIds !== null) {
            if ($ministryIds === []) {
                return [];
            }
            $query->usePositionQuery()
                    ->filterByMinistryId($ministryIds, Criteria::IN)
                ->endUse();
        }

        return iterator_to_array($query->orderById()->find(), false);
    }

    /**
     * The active-qualification person ids for one position — the eligibility
     * answer #9709 will ask for when it assigns.
     *
     * @return int[]
     */
    public function getQualifiedPersonIds(int $positionId): array
    {
        $ids = [];
        $rows = VolunteerQualificationQuery::create()
            ->filterByPositionId($positionId)
            ->filterByActive(true)
            ->select(['PersonId'])
            ->find();

        foreach ($rows as $personId) {
            $ids[] = (int) $personId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * The whole matrix body in ONE query: person id → position id →
     * qualification id, for every position asked about.
     *
     * §5.4 requires the matrix to render 15–200 people without re-fetching per
     * cell, and this is what makes that true — the screen fetches positions,
     * pool people and this map, then draws. The qualification id is carried
     * (rather than just a boolean) so unticking a box can revoke the exact row
     * without a lookup request per click.
     *
     * @param int[] $positionIds
     *
     * @return array<int, array<int, int>>
     */
    public function getQualificationsByPerson(array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }

        $map = [];
        $rows = VolunteerQualificationQuery::create()
            ->filterByPositionId($positionIds, Criteria::IN)
            ->filterByActive(true)
            ->select(['Id', 'PersonId', 'PositionId'])
            ->find();

        foreach ($rows as $row) {
            $personId = (int) $row['PersonId'];
            $map[$personId][(int) $row['PositionId']] = (int) $row['Id'];
        }

        return $map;
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

    /**
     * A pool is owned by a ministry or by a team, and the check follows the
     * owner (§4.6). A pool whose team has since been deleted is unreachable
     * rather than open to everyone.
     *
     * @throws VolunteerSetupException
     */
    private function assertCanManagePool(User $actor, VolunteerPool $pool): void
    {
        if ($pool->getOwnerType() === VolunteerPool::OWNER_TYPE_MINISTRY) {
            $this->assertCanManageMinistry($actor, (int) $pool->getOwnerId());

            return;
        }

        $this->assertCanManageTeam($actor, (int) $pool->getOwnerId());
    }

    /**
     * Which team ids contribute pools for this ministry view.
     *
     * `$teamId` narrows to one team (validated against the ministry so a team
     * from elsewhere cannot be borrowed); `$visibleTeamIds` narrows to what a
     * team leader may see. Both are intersected with the ministry's own teams,
     * in ONE query.
     *
     * @param int[]|null $visibleTeamIds
     *
     * @return int[]
     */
    private function resolvePoolTeamIds(int $ministryId, ?int $teamId, ?array $visibleTeamIds): array
    {
        $query = VolunteerTeamQuery::create()->filterByMinistryId($ministryId);

        if ($teamId !== null) {
            $query->filterById($teamId);
        }

        if ($visibleTeamIds !== null) {
            if ($visibleTeamIds === []) {
                return [];
            }
            $query->filterById($visibleTeamIds, Criteria::IN);
        }

        $teamIds = [];
        foreach ($query->select(['Id'])->find() as $id) {
            $teamIds[] = (int) $id;
        }

        return $teamIds;
    }

    /**
     * Insert or reactivate the single row for (person, position). Shared by the
     * one-at-a-time grant and the cart bulk grant so the two cannot drift on
     * what "re-grant" means.
     */
    private function writeQualification(
        int $personId,
        VolunteerPosition $position,
        User $actor,
        ?string $notes
    ): VolunteerQualification {
        $qualification = $this->findQualification($personId, (int) $position->getId());

        if ($qualification === null) {
            $qualification = new VolunteerQualification();
            $qualification->setPersonId($personId);
            $qualification->setPositionId((int) $position->getId());
        }

        // A re-grant re-stamps who granted it and when: that is the fact a
        // coordinator wants to see, and the row is the only place it lives.
        $qualification->setActive(true);
        // Naive wall-clock in sTimeZone, like every other V2 timestamp (§2.0).
        $qualification->setGrantedDate(DateTimeUtils::getNowDateTime());
        $qualification->setGrantedByPersonId((int) $actor->getId());

        if ($notes !== null) {
            $qualification->setNotes($this->normalizeDescription($notes));
        }

        $qualification->save();

        return $qualification;
    }

    /** An absent or blank pool label is stored as NULL, never as ''. */
    private function normalizeLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $label = trim($label);

        return $label === '' ? null : $label;
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
     * The check §2.6 explicitly calls for, kept in PHP even though
     * `vpos_ministry_team_name_uidx` now covers every position (D18 made
     * `vpos_vtem_ID` `NOT NULL`, so the "MySQL treats NULLs as distinct" hole is
     * closed). The index would raise a driver error rather than a 409, and its
     * case-insensitivity is a property of the collation rather than of the design
     * — this check owns both the status code and the message.
     *
     * @throws VolunteerSetupException
     */
    private function assertPositionNameFree(int $ministryId, int $teamId, string $name, ?int $exceptPositionId): void
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
