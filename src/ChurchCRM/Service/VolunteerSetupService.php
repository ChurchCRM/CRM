<?php

namespace ChurchCRM\Service;

use ChurchCRM\Exceptions\VolunteerSetupException;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Map\VolunteerMinistryTableMap;
use ChurchCRM\model\ChurchCRM\Map\VolunteerTeamTableMap;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
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
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
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
 *
 * D19 rewrote the pool half. There is no `volunteer_pool_vpol` and no linking
 * step: a ministry owns exactly ONE core Group, created with it here, carrying
 * `group_grp.grp_ministry_id`. This class is therefore the only place that
 * writes a pool Group's membership from V2, and it does so through the Propel
 * model inside `VolunteerPoolWriter::run()` — so the plugin hooks still fire and
 * the model's `bManageGroups` rule is bypassed only where this class has already
 * answered the authorization question itself.
 */
class VolunteerSetupService
{
    /** `list_lst.lst_ID` of the group-type list (F11). */
    private const GROUP_TYPE_LIST_ID = 3;

    /** The group type a ministry's volunteer pool Group is given (D19). */
    private const MINISTRY_GROUP_TYPE_NAME = 'Ministry';

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
     * D19: a ministry is never pool-less either. Its volunteer pool is one ordinary
     * core Group, created here in the SAME transaction, named after the ministry,
     * typed "Ministry" and carrying `grp_ministry_id`. That column is what the
     * Propel hooks read to let this ministry's coordinator write the group without
     * the global Manage Groups flag, and what the Groups module reads to say the
     * group is managed elsewhere. There is no link table and nothing to link: the
     * Group IS the roster (D1), and now it has an owner.
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

            // The actor is a global volunteer manager, which is NOT the same thing as
            // holding `bManageGroups` — so the model hooks would refuse this save on the
            // way in. The managed-write context is how a V2 service says "I have already
            // authorized this" (D19); the authorization is the isGlobalManager() check
            // at the top of this method.
            $group = VolunteerPoolWriter::run(fn (): Group => $this->createPoolGroup($ministry, $connection));

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        $this->logger->info('Volunteer ministry created', [
            'ministryId' => $ministry->getId(),
            'name' => $ministry->getName(),
            'defaultTeamId' => $team->getId(),
            'poolGroupId' => $group->getId(),
            'actor' => $actor->getId(),
        ]);

        return $ministry;
    }

    /**
     * The ministry's volunteer pool Group (D19).
     *
     * `grp_Name` is `VARCHAR(50)` and `vmin_Name` is `VARCHAR(100)` (F2), so a long
     * ministry name is TRUNCATED rather than refused: the ministry name is the thing
     * people read, the group name is a label on the same object, and failing to create a
     * ministry because its name is 63 characters long would be absurd. Group names are
     * not unique in `group_grp`, so a collision with an existing group is legal and is
     * left alone.
     */
    private function createPoolGroup(VolunteerMinistry $ministry, $connection): Group
    {
        $group = new Group();
        $group->setName(mb_substr((string) $ministry->getName(), 0, 50));
        $group->setType($this->ministryGroupTypeId());
        $group->setDescription(sprintf(
            gettext('The volunteer pool of the %s ministry.'),
            (string) $ministry->getName()
        ));
        $group->setActive(true);
        $group->setIncludeInEmailExport(true);
        $group->setMinistryId((int) $ministry->getId());
        $group->save($connection);

        return $group;
    }

    /**
     * The `list_lst` option id of the "Ministry" group type (F11).
     *
     * Looked up by NAME rather than hardcoded to 1: the group-type list is editable in
     * every installation and the seed's numbering is a fact about the seed, not about
     * ChurchCRM. When an installation has renamed or removed the option the type is
     * created rather than guessed, so the pool group still lands in a type that says
     * what it is.
     */
    private function ministryGroupTypeId(): int
    {
        $existing = ListOptionQuery::create()
            ->filterById(self::GROUP_TYPE_LIST_ID)
            ->findOneByOptionName(self::MINISTRY_GROUP_TYPE_NAME);

        if ($existing !== null) {
            return (int) $existing->getOptionId();
        }

        $highest = 0;
        foreach (ListOptionQuery::create()->filterById(self::GROUP_TYPE_LIST_ID)->find() as $option) {
            $highest = max($highest, (int) $option->getOptionId(), (int) $option->getOptionSequence());
        }

        $created = new ListOption();
        $created->setId(self::GROUP_TYPE_LIST_ID);
        $created->setOptionId($highest + 1);
        $created->setOptionSequence($highest + 1);
        $created->setOptionName(self::MINISTRY_GROUP_TYPE_NAME);
        $created->save();

        $this->logger->info('Created the "Ministry" group type for a volunteer pool group', [
            'optionId' => $created->getOptionId(),
        ]);

        return (int) $created->getOptionId();
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
     * D19 adds two more writable keys and one side effect. `helpWanted` /
     * `helpWantedText` are the Open Opportunities advert (§5.6); renaming the ministry
     * RENAMES ITS POOL GROUP, because a group called "Coffee Bar" sitting under a
     * ministry now called "Cafe" is exactly the drift the owning column exists to stop.
     *
     * @param array{name?: string, description?: string|null, active?: bool, helpWanted?: bool, helpWantedText?: string|null} $fields
     *
     * @throws VolunteerSetupException
     */
    public function updateMinistry(VolunteerMinistry $ministry, array $fields, User $actor): VolunteerMinistry
    {
        $this->assertCanManageMinistry($actor, (int) $ministry->getId());

        $renamed = false;
        if (array_key_exists('name', $fields)) {
            $name = $this->requireName((string) $fields['name'], gettext('A ministry name is required'));
            $this->assertMinistryNameFree($name, (int) $ministry->getId());
            $renamed = $name !== (string) $ministry->getName();
            $ministry->setName($name);
        }

        if (array_key_exists('description', $fields)) {
            $ministry->setDescription($this->normalizeDescription($fields['description']));
        }

        if (array_key_exists('active', $fields)) {
            $ministry->setActive((bool) $fields['active']);
        }

        if (array_key_exists('helpWanted', $fields)) {
            $ministry->setHelpWanted((bool) $fields['helpWanted']);
        }

        if (array_key_exists('helpWantedText', $fields)) {
            $ministry->setHelpWantedText($this->normalizeHelpWantedText($fields['helpWantedText']));
        }

        $ministry->save();

        if ($renamed) {
            $group = $this->getPoolGroup((int) $ministry->getId());
            if ($group !== null) {
                // Same managed-write reason as createMinistry(): the coordinator doing
                // the rename need not hold `bManageGroups`, and this service has already
                // decided they may (assertCanManageMinistry above).
                VolunteerPoolWriter::run(function () use ($group, $ministry): void {
                    $group->setName(mb_substr((string) $ministry->getName(), 0, 50));
                    $group->save();
                });
            }
        }

        $this->logger->info('Volunteer ministry updated', [
            'ministryId' => $ministry->getId(),
            'renamedPoolGroup' => $renamed,
            'actor' => $actor->getId(),
        ]);

        return $ministry;
    }

    /**
     * Delete a ministry. **Manager-only** (§4.6), and refused with 409 the moment
     * any occurrence or assignment hangs off it — at that point there is service
     * history to protect and `Active = 0` is the right answer (§2.3).
     *
     * When it does go through, the foreign keys take teams, positions,
     * qualifications, schedules and requirements with it. Two things do not, and are
     * removed explicitly inside the same transaction:
     *
     *   * `volunteer_scope_vscp` — its target column is polymorphic and carries no
     *     foreign key (§2.15), so the installation would be left with grants pointing
     *     at nothing.
     *   * the ministry's **pool Group** — `group_grp.grp_ministry_id` is `ON DELETE SET
     *     NULL` on purpose (D19), because a cascade from a V2 table to a core table is
     *     how a church loses a group to a foreign key it never knew about. Removing it
     *     is this method's decision, taken in the open, and it is the ONLY path allowed
     *     to: `Group::preDelete()` refuses a group with a ministry id unless the
     *     managed-write context is open.
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

            // Before the ministry row goes, or `grp_ministry_id` is already NULL and the
            // group has become an ordinary orphan nobody will recognise.
            $poolGroup = $this->getPoolGroup($ministryId);
            $removedGroupId = null;
            if ($poolGroup !== null) {
                $removedGroupId = (int) $poolGroup->getId();
                VolunteerPoolWriter::run(function () use ($poolGroup, $removedGroupId, $connection): void {
                    // The membership rows are the group's own and mean nothing without
                    // it — the same cascade `DELETE /api/groups/{id}` performs.
                    Person2group2roleP2g2rQuery::create()
                        ->filterByGroupId($removedGroupId)
                        ->delete($connection);
                    $poolGroup->delete($connection);
                });
            }

            $ministry->delete($connection);
            $connection->commit();

            $this->logger->info('Volunteer ministry deleted', [
                'ministryId' => $ministryId,
                'removedScopeRows' => $removedScopes,
                'removedPoolGroupId' => $removedGroupId,
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

    // ══ The pool Group (D19) ══════════════════════════════════════════════
    //
    // D1 still holds — a Group IS the roster, and V2 copies nobody. What D19
    // changed is the link: there is no `volunteer_pool_vpol` and no link table
    // at all, because a ministry owns exactly ONE Group and that Group carries
    // `grp_ministry_id`. Everything below therefore reads
    // `person2group2role_p2g2r` live through that one group; there is no cache
    // to invalidate and no second source of truth.
    //
    // Writes go through the Propel membership model rather than raw SQL, so the
    // `Hooks::GROUP_MEMBER_ADDED` / `GROUP_MEMBER_REMOVED` plugin events and the
    // person timeline Note the Groups module writes keep firing for a pool edit
    // exactly as they do for a Groups-module edit.

    /**
     * The ministry's pool Group, or null on an installation upgraded mid-flight.
     *
     * `GroupQuery::preSelect()` injects a LEFT JOIN, a `COUNT()` and a `GROUP BY` into
     * every query built from it (F22), and this inherits them. Accepted rather than
     * worked around: it is one indexed single-row lookup, and the alternative is raw
     * SQL against `group_grp` — the first ORM bypass for a read anywhere in V2. Member
     * COUNTS still come from `countGroupMembers()`, never from that virtual column.
     */
    public function getPoolGroup(int $ministryId): ?Group
    {
        return GroupQuery::create()->findOneByMinistryId($ministryId);
    }

    /**
     * The pool Group, or a 404 — for the endpoints that cannot do anything useful
     * without one.
     *
     * @throws VolunteerSetupException
     */
    public function requirePoolGroup(int $ministryId): Group
    {
        $group = $this->getPoolGroup($ministryId);
        if ($group === null) {
            throw VolunteerSetupException::notFound(gettext('This ministry has no volunteer pool group'));
        }

        return $group;
    }

    /**
     * Add somebody to the ministry's volunteer pool, with the Group's default role.
     *
     * Idempotent: already being in the pool is success, not a 409 — "put this person
     * in the pool" is a statement about the end state, and the qualification path
     * and the "I'd like to help" button both rely on being able to say it without
     * asking first.
     *
     * `$actor` is checked here even though the route's middleware usually has too,
     * so a non-HTTP caller cannot skip it (§4.5 layer three). The managed-write
     * context is opened AFTER that check, never before it.
     *
     * @throws VolunteerSetupException 403 outside scope, 404 for an unknown person
     */
    public function addPoolMember(int $ministryId, int $personId, User $actor): bool
    {
        $this->assertCanManageMinistry($actor, $ministryId);

        if (PersonQuery::create()->findPk($personId) === null) {
            throw VolunteerSetupException::notFound(gettext('Person not found'));
        }

        return $this->joinPoolGroup($this->requirePoolGroup($ministryId), $personId);
    }

    /**
     * Put a list of people in the ministry's volunteer pool — the Cart sink (P5/P6).
     *
     * The loop lives here rather than in `Cart`, matching the precedent the design
     * names: the route reads `Cart::getCartPeople()` and hands the ids to the service.
     * Authorization is checked ONCE, before the loop, because every row targets the
     * same ministry — and the pool Group is resolved once for the same reason.
     *
     * Per person this is exactly `addPoolMember()`: idempotent, and somebody already
     * in the pool is counted rather than thrown over. Ids that name nobody are skipped
     * silently — a cart can outlive a deleted person, and failing the whole batch over
     * one stale id would be worse than ignoring it.
     *
     * Nothing is qualified here. Being in the pool is candidacy; the tick on the
     * Volunteers grid is eligibility (§2.5), and the coordinator does that second.
     *
     * @param int[] $personIds
     *
     * @return array{added: int, alreadyMembers: int}
     *
     * @throws VolunteerSetupException 403 outside scope, 404 when the ministry has no pool group
     */
    public function addPoolMembers(int $ministryId, array $personIds, User $actor): array
    {
        $this->assertCanManageMinistry($actor, $ministryId);

        $group = $this->requirePoolGroup($ministryId);

        $added = 0;
        $alreadyMembers = 0;
        $seen = [];

        foreach ($personIds as $rawId) {
            $personId = (int) $rawId;
            if ($personId <= 0 || isset($seen[$personId])) {
                continue;
            }
            $seen[$personId] = true;

            if (PersonQuery::create()->findPk($personId) === null) {
                continue;
            }

            if ($this->joinPoolGroup($group, $personId)) {
                ++$added;
            } else {
                ++$alreadyMembers;
            }
        }

        $this->logger->info('Volunteer pool members added in bulk', [
            'ministryId' => $ministryId,
            'groupId' => $group->getId(),
            'added' => $added,
            'alreadyMembers' => $alreadyMembers,
            'actor' => $actor->getId(),
        ]);

        return ['added' => $added, 'alreadyMembers' => $alreadyMembers];
    }

    /**
     * Put a person in a pool Group whose authorization the CALLER has already
     * decided — the qualification path (§4.6 puts no membership condition on
     * granting, so qualifying somebody simply brings them in) and the member-side
     * "I'd like to help" action.
     *
     * Returns true when a row was created, false when the person was already there.
     * The `Hooks::GROUP_MEMBER_ADDED` plugin event fires on the create, matching
     * `POST /api/groups/{id}/addperson/{id}` (G3).
     */
    public function joinPoolGroup(Group $group, int $personId): bool
    {
        $existing = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId((int) $group->getId())
            ->filterByPersonId($personId)
            ->findOne();

        if ($existing !== null) {
            return false;
        }

        VolunteerPoolWriter::run(function () use ($group, $personId): void {
            $membership = new Person2group2roleP2g2r();
            $membership->setGroupId((int) $group->getId());
            $membership->setPersonId($personId);
            $membership->setRoleId((int) $group->getDefaultRole());
            $membership->save();

            HookManager::doAction(
                Hooks::GROUP_MEMBER_ADDED,
                $membership,
                $group,
                PersonQuery::create()->findPk($personId)
            );
        });

        $this->logger->info('Volunteer pool member added', [
            'ministryId' => $group->getMinistryId(),
            'groupId' => $group->getId(),
            'personId' => $personId,
        ]);

        return true;
    }

    /**
     * Take somebody out of the ministry's volunteer pool.
     *
     * Removing them from the pool does NOT revoke their qualifications: the two are
     * independent since D19, and silently un-qualifying somebody because a
     * coordinator tidied a roster would be a surprise with service history attached.
     *
     * @throws VolunteerSetupException 403 outside scope, 404 when they are not in it
     */
    public function removePoolMember(int $ministryId, int $personId, User $actor): void
    {
        $this->assertCanManageMinistry($actor, $ministryId);

        $group = $this->requirePoolGroup($ministryId);
        $membership = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId((int) $group->getId())
            ->filterByPersonId($personId)
            ->findOne();

        if ($membership === null) {
            throw VolunteerSetupException::notFound(gettext('That person is not in this volunteer pool'));
        }

        VolunteerPoolWriter::run(static function () use ($membership, $group, $personId): void {
            $membership->delete();
            HookManager::doAction(Hooks::GROUP_MEMBER_REMOVED, $personId, $group);
        });

        $this->logger->info('Volunteer pool member removed', [
            'ministryId' => $ministryId,
            'groupId' => $group->getId(),
            'personId' => $personId,
            'actor' => $actor->getId(),
        ]);
    }

    /** Is this person in the ministry's pool Group? One indexed row lookup. */
    public function isInPool(int $ministryId, int $personId): bool
    {
        $group = $this->getPoolGroup($ministryId);
        if ($group === null) {
            return false;
        }

        return Person2group2roleP2g2rQuery::create()
            ->filterByGroupId((int) $group->getId())
            ->filterByPersonId($personId)
            ->count() > 0;
    }

    /**
     * The people in the pool. Nothing is copied and nothing is cached — the group
     * membership table is read every time, so somebody added in the Groups module
     * appears here on the next page load (D1).
     *
     * The `$teamId` parameter is accepted and ignored: a ministry has ONE pool since
     * D19, and every team under it draws on the same people. It is kept so the two
     * dozen call sites that pass a schedule's team id do not each have to learn that,
     * and so a future per-team pool is a change here rather than everywhere.
     *
     * @return int[] person ids, ascending
     */
    public function getPoolPersonIds(int $ministryId, ?int $teamId = null): array
    {
        return array_keys($this->getPoolMembership($ministryId, $teamId));
    }

    /**
     * The same people, keyed the way the qualification matrix wants them: person id
     * → the pool group ids they arrived through. Since D19 that list is always the
     * ministry's one group, and the shape is kept because the matrix and the member
     * rows already speak it.
     *
     * One query over `person2group2role_p2g2r`.
     *
     * @param int[]|null $visibleTeamIds accepted and ignored — see getPoolPersonIds()
     *
     * @return array<int, int[]>
     */
    public function getPoolMembership(int $ministryId, ?int $teamId = null, ?array $visibleTeamIds = null): array
    {
        $group = $this->getPoolGroup($ministryId);
        if ($group === null) {
            return [];
        }

        $groupId = (int) $group->getId();
        $membership = [];
        $rows = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($groupId)
            ->select(['PersonId'])
            ->find();

        foreach ($rows as $personId) {
            $membership[(int) $personId] = [$groupId];
        }

        ksort($membership);

        return $membership;
    }

    /**
     * Member counts for a set of groups, in ONE query.
     *
     * Deliberately not `GroupQuery`'s `memberCount` virtual column: that comes from a
     * `preSelect()` which also injects a second LEFT JOIN and a `GROUP BY` into every
     * query built from it (F22). Counting the membership table directly is one plain
     * aggregate and says what it does.
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

    // ══ Help wanted (D19) ═════════════════════════════════════════════════

    /**
     * Every ministry currently advertising for help, active ones only.
     *
     * No authorization: this is what the Open Opportunities page shows to any
     * signed-in volunteer, and a ministry that has switched the advert ON has asked
     * to be seen. Deactivated ministries are excluded whatever their flag says — an
     * inactive ministry is not running, and inviting somebody to join it would be a
     * dead end.
     *
     * @return VolunteerMinistry[]
     */
    public function listHelpWantedMinistries(): array
    {
        return iterator_to_array(
            VolunteerMinistryQuery::create()
                ->filterByHelpWanted(true)
                ->filterByActive(true)
                ->orderByName()
                ->find(),
            false
        );
    }

    /**
     * "I'd like to help" (D19): put the person in the ministry's pool if they are not
     * already there, and tell whoever runs it.
     *
     * The actor is the SESSION person and is passed as an id rather than a `User`,
     * because there is no permission to check — any signed-in volunteer may offer to
     * help a ministry that has asked for help, and that is the whole authorization
     * rule. What there IS to check is the ministry's own consent: `403` when the
     * advert is off, so a volunteer cannot add themselves to an arbitrary roster by
     * guessing an id.
     *
     * The pool write is a managed write for exactly this reason: the person is neither
     * an administrator nor a coordinator, and this method — having checked the advert —
     * is the thing that decided they may.
     *
     * Notification goes to every coordinator of the ministry, or to the global
     * volunteer managers when it has none. When there is nobody at all the offer still
     * succeeds: the volunteer did their part, and a silent failure on their screen
     * would be the wrong half to punish.
     *
     * @return array{joinedPool: bool, notified: int}
     *
     * @throws VolunteerSetupException 403 when the ministry is not asking for help
     */
    public function recordHelpOffer(VolunteerMinistry $ministry, int $personId): array
    {
        if (!$ministry->getHelpWanted()) {
            throw VolunteerSetupException::forbidden(gettext('This ministry is not asking for help right now'));
        }

        $ministryId = (int) $ministry->getId();
        $group = $this->requirePoolGroup($ministryId);
        $joinedPool = $this->joinPoolGroup($group, $personId);

        $recipients = $this->authz->getCoordinatorPersonIds($ministryId);
        if ($recipients === []) {
            $recipients = $this->authz->getGlobalManagerPersonIds();
        }

        // Never tell somebody about their own offer: a coordinator who volunteers for
        // their own ministry does not need mail from themselves.
        $recipients = array_values(array_filter(
            $recipients,
            static fn (int $candidate): bool => $candidate !== $personId
        ));

        if ($recipients === []) {
            $this->logger->warning('Volunteer help offer has nobody to notify', [
                'ministryId' => $ministryId,
                'personId' => $personId,
            ]);
        } else {
            (new VolunteerNotificationService())
                ->enqueueHelpOffer($ministryId, $personId, $recipients, $joinedPool);
        }

        $this->logger->info('Volunteer help offered', [
            'ministryId' => $ministryId,
            'personId' => $personId,
            'joinedPool' => $joinedPool,
            'notified' => count($recipients),
        ]);

        return ['joinedPool' => $joinedPool, 'notified' => count($recipients)];
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
    // be in the pool first. §2.5 is explicit that "pool ≠ eligibility", and §4.6
    // puts no membership condition on granting — the picker may name ANYONE.
    //
    // D19 adds the other half of that: qualifying somebody who is not in the
    // ministry's pool Group PUTS THEM IN IT, in the same transaction. A person a
    // coordinator has just said may serve is by definition one of the ministry's
    // volunteers, and leaving them outside the roster was the source of the
    // "qualified but the rotation never offers them" surprise. Revoking a
    // qualification never takes them back out: that would quietly remove somebody
    // from a roster because one of several qualifications ended.

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

        $connection = Propel::getWriteConnection(VolunteerMinistryTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $qualification = $this->writeQualification($personId, $position, $actor, $notes);
            $joinedPool = $this->qualifyIntoPool((int) $position->getMinistryId(), $personId);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        $this->logger->info('Volunteer qualification granted', [
            'qualificationId' => $qualification->getId(),
            'personId' => $personId,
            'positionId' => $position->getId(),
            'addedToPool' => $joinedPool,
            'actor' => $actor->getId(),
        ]);

        return $qualification;
    }

    /**
     * D19: a newly qualified person joins the ministry's pool Group.
     *
     * The caller has already authorized the qualification (`assertCanManagePosition`),
     * and a team leader may grant on their own team's positions without coordinating
     * the ministry — so this is a managed write, decided by the grant, not a second
     * permission question. Returns true when a membership row was actually created.
     */
    private function qualifyIntoPool(int $ministryId, int $personId): bool
    {
        $group = $this->getPoolGroup($ministryId);
        if ($group === null) {
            return false;
        }

        return $this->joinPoolGroup($group, $personId);
    }

    /**
     * Revoke a qualification by **deactivating** it (§2.7). The row survives,
     * so the grant history and every assignment that predates the revocation
     * stay readable.
     *
     * D19: this does NOT remove the person from the ministry's pool Group. They may
     * hold other qualifications, they are still one of the ministry's volunteers, and
     * un-rostering somebody as a side effect of ending one qualification is a surprise
     * a coordinator cannot undo without noticing it happened. Removing them from the
     * pool is `removePoolMember()`, a separate, deliberate act.
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

    /**
     * "Help wanted" prose (D19). Blank is stored as NULL, never as '', so the advert
     * is either something to show or nothing at all — an empty string would render as
     * a ministry card with a blank paragraph under it.
     */
    private function normalizeHelpWantedText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim($text);

        return $text === '' ? null : $text;
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
