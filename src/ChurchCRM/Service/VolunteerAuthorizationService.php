<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Map\UserTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\model\ChurchCRM\VolunteerAssignment;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSchedule;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScope;
use ChurchCRM\model\ChurchCRM\VolunteerScopeQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSwap;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Log\LoggerInterface;

/**
 * The single decision point for Volunteer Management v2 authorization (#9706, epic #9701).
 *
 * Five tiers (design §4.1, D12):
 *
 *   Administrator  → true for everything
 *   Global Manager → true for every ministry and every team
 *   Coordinator    → true for their ministries and every team under them
 *   Team Leader    → true for their teams only
 *   Volunteer      → true only for rows whose person id is their own
 *
 * The administrator bypass lives in exactly one place — User::isVolunteerManagerEnabled(),
 * reached through isGlobalManager() — and every other predicate in this class calls
 * canManageMinistry() or canManageTeam(), which begin with that call. Nothing else in V2
 * may test isAdmin() for a volunteer decision.
 *
 * Scope is keyed on the PERSON, not the user (§2.15): a scope may be granted before the
 * person has a login, and User::getId() already returns the person id (F4), so no join
 * is needed.
 *
 * Every predicate takes the entity id rather than reading it from the caller's context, so
 * a future core AuthorizationService (#8758, which has zero code in the tree today) can
 * absorb this class without call-site churn — the same convention User::canReadPerson(int)
 * already follows (§4.9).
 *
 * Deliberately NOT here: authorization in Propel lifecycle hooks. AuthService::
 * requireUserGroupMembership() reads $_SESSION flags that APITokenAuthentication never
 * sets, so ORM-hook authorization silently degrades to admin-only for API-key callers
 * (A12/F21). Every decision below works identically for session and API-key callers.
 *
 * READ SCOPING NOTE for later issues (§4.4): a list endpoint must filter in the QUERY,
 * never in PHP after hydration — but it must first ask isGlobalManager(), because
 * getManagedMinistryIds() returns only the caller's explicit grants and an administrator
 * has none. The shape is:
 *
 *     if (!$authz->isGlobalManager($user)) {
 *         $ids = $authz->getManagedMinistryIds($user);
 *         if ($ids === []) { return []; }          // an empty allow-list means no rows
 *         $query->filterByMinistryId($ids, Criteria::IN);
 *     }
 */
class VolunteerAuthorizationService
{
    public const SCOPE_MINISTRY = VolunteerScope::TYPE_MINISTRY;
    public const SCOPE_TEAM = VolunteerScope::TYPE_TEAM;

    /**
     * Per-request memoisation of the raw scope rows, keyed by person id
     * (design §4.4). Avoids the N+1 an entity middleware would otherwise cause
     * when several entities are resolved on one request.
     *
     * @var array<int, array{ministry: int[], team: int[]}>
     */
    private array $scopeCache = [];

    /**
     * Per-request memoisation of `getManageableMinistries()`, keyed by person id.
     * Static on purpose — see that method's docblock.
     *
     * @var array<int, array<int, string>>
     */
    private static array $manageableMinistryMemo = [];

    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    // ── Tier predicates ────────────────────────────────────────────────────

    /**
     * Administrator or global Volunteer Manager.
     *
     * Delegates to the User model so the administrator bypass, the EditSelf-exclusive
     * short-circuit and the `sVolunteerVersion` rollout gate are all decided once.
     */
    public function isGlobalManager(User $user): bool
    {
        return $user->isVolunteerManagerEnabled();
    }

    /**
     * Does this user hold at least one ministry or team scope row?
     *
     * Pure data: it answers "is there a grant", not "is this person allowed to be a
     * coordinator". The tier rules (rollout state, EditSelf exclusivity, admin bypass)
     * live in User::isVolunteerCoordinatorEnabled(), which calls this last.
     */
    public function hasAnyScope(User $user): bool
    {
        $scopes = $this->loadScopes($user);

        return $scopes['ministry'] !== [] || $scopes['team'] !== [];
    }

    public function canManageMinistry(User $user, int $ministryId): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        return in_array($ministryId, $this->getManagedMinistryIds($user), true);
    }

    public function canManageTeam(User $user, int $teamId): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        return in_array($teamId, $this->getManagedTeamIds($user), true);
    }

    /**
     * A position belongs to its team (D18 — there is no team-less position), so its
     * team leader may touch it, and so may the ministry coordinator above them
     * through `canManageTeam()` (§4.4, §4.6).
     */
    public function canManagePosition(User $user, int $positionId): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        $position = VolunteerPositionQuery::create()->findPk($positionId);
        if ($position === null) {
            return false;
        }

        return $this->canManageTeam($user, (int) $position->getTeamId());
    }

    /** Same shape as `canManagePosition()`: a schedule always names a team (D18). */
    public function canManageSchedule(User $user, VolunteerSchedule $schedule): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        return $this->canManageTeam($user, (int) $schedule->getTeamId());
    }

    public function canManageOccurrence(User $user, VolunteerOccurrence $occurrence): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
        if ($schedule === null) {
            return false;
        }

        return $this->canManageSchedule($user, $schedule);
    }

    public function canManageAssignment(User $user, VolunteerAssignment $assignment): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $assignment->getOccurrenceId());
        if ($occurrence === null) {
            return false;
        }

        return $this->canManageOccurrence($user, $occurrence);
    }

    public function canManageSwap(User $user, VolunteerSwap $swap): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        $assignment = VolunteerAssignmentQuery::create()->findPk((int) $swap->getAssignmentId());
        if ($assignment === null) {
            return false;
        }

        return $this->canManageAssignment($user, $assignment);
    }

    /**
     * The volunteer tier: a person may only answer for their own assignment.
     * Identity only — nothing is persisted for it (§4.1).
     */
    public function canRespondToAssignment(User $user, VolunteerAssignment $assignment): bool
    {
        return (int) $assignment->getPersonId() === (int) $user->getId();
    }

    // ── Scope listing, used to scope LIST queries ──────────────────────────

    /**
     * Ministry ids this user may administer; [] for a plain volunteer.
     *
     * Returns explicit grants ONLY — an administrator or global manager has none, so a
     * caller scoping a list query must ask isGlobalManager() first (see the class docblock).
     *
     * @return int[]
     */
    public function getManagedMinistryIds(User $user): array
    {
        return $this->loadScopes($user)['ministry'];
    }

    /**
     * The ministries this user may administer, id => name, ordered by name.
     *
     * This is what the sidebar's **Ministries** heading lists: one entry per
     * ministry, linking to `/volunteer/ministries/{id}`. It is deliberately
     * NOT `getManagedMinistryIds()` plus a second query at the call site —
     * the two tiers answer differently and the difference is the whole point:
     *
     *   - a global manager or an administrator holds no explicit grants, so
     *     their list is every **active** ministry;
     *   - anybody else gets exactly the ministries they hold a `ministry`
     *     scope on, active or not — a coordinator of a ministry that was just
     *     deactivated still has to be able to reach it, and since the
     *     ministries list page was retired the sidebar is the only way in;
     *   - a pure **team leader** holds only `team` scopes, so the list is
     *     empty. Leading a team is not administering the ministry above it
     *     (§4.6), and `/volunteer/ministries/{id}` would refuse them.
     *
     * Ids and names only — this runs on **every** page of the application, so
     * it never hydrates a model object.
     *
     * Memoised per request in a STATIC, unlike the instance-level
     * `$scopeCache`: `Menu::buildMenuItems()` constructs its own service and
     * every route handler constructs another, so an instance memo would not
     * make "one query per request" true. A static is safe here for the same
     * reason it is safe in `User::$volunteerCoordinatorMemo` — nothing
     * serializes this class into the PHP session, and a static never outlives
     * the request.
     *
     * @return array<int, string> ministry id => ministry name
     */
    public function getManageableMinistries(User $user): array
    {
        $personId = (int) $user->getId();
        if (array_key_exists($personId, self::$manageableMinistryMemo)) {
            return self::$manageableMinistryMemo[$personId];
        }

        $query = VolunteerMinistryQuery::create();

        if ($this->isGlobalManager($user)) {
            $query->filterByActive(true);
        } else {
            $ministryIds = $this->getManagedMinistryIds($user);
            if ($ministryIds === []) {
                return self::$manageableMinistryMemo[$personId] = [];
            }
            $query->filterById($ministryIds, Criteria::IN);
        }

        $ministries = [];
        foreach ($query->orderByName()->select(['Id', 'Name'])->find()->toArray() as $row) {
            $ministries[(int) $row['Id']] = (string) $row['Name'];
        }

        return self::$manageableMinistryMemo[$personId] = $ministries;
    }

    /**
     * Team ids: own team scopes ∪ every team under a managed ministry (§4.4).
     * That union is what makes the hierarchy real rather than two independent lists.
     *
     * @return int[]
     */
    public function getManagedTeamIds(User $user): array
    {
        $scopes = $this->loadScopes($user);
        $teamIds = $scopes['team'];

        if ($scopes['ministry'] !== []) {
            $inherited = VolunteerTeamQuery::create()
                ->filterByMinistryId($scopes['ministry'], Criteria::IN)
                ->select(['Id'])
                ->find()
                ->toArray();
            foreach ($inherited as $id) {
                $teamIds[] = (int) $id;
            }
        }

        return array_values(array_unique($teamIds));
    }

    /**
     * Coordinators and team leaders who should be alerted about this ministry/team.
     * The team's own leaders come first, then the ministry's coordinators; #9710 mails them.
     *
     * @return int[] person ids, deduplicated, team leaders first
     */
    public function getCoordinatorPersonIds(int $ministryId, ?int $teamId = null): array
    {
        $personIds = [];

        if ($teamId !== null) {
            foreach ($this->findScopeRows(self::SCOPE_TEAM, $teamId) as $scope) {
                $personIds[] = (int) $scope->getPersonId();
            }
        }

        foreach ($this->findScopeRows(self::SCOPE_MINISTRY, $ministryId) as $scope) {
            $personIds[] = (int) $scope->getPersonId();
        }

        return array_values(array_unique($personIds));
    }

    /**
     * Everyone who holds global volunteer authority — the people to tell when a
     * ministry has no coordinator of its own (D19's "help wanted" fallback).
     *
     * Read from `user_usr`, because that is where the tier lives: a global volunteer
     * manager is a FLAG on a login (`usr_VolunteerManager`, #9706) and an administrator
     * is `usr_Admin`, neither of which has a `volunteer_scope_vscp` row. `User::getId()`
     * IS the person id (F4), so no join is needed.
     *
     * EditSelf-exclusive logins are excluded: §4.3 says the volunteer persona is never a
     * manager, and `isVolunteerManagerEnabled()` would refuse them anyway — filtering
     * here keeps a mail from being addressed to somebody the rest of V2 would turn away.
     *
     * @return int[] person ids, deduplicated
     */
    public function getGlobalManagerPersonIds(): array
    {
        $personIds = [];

        $users = UserQuery::create()
            ->condition('isManager', UserTableMap::COL_USR_VOLUNTEERMANAGER . ' = ?', true)
            ->condition('isAdmin', UserTableMap::COL_USR_ADMIN . ' = ?', true)
            ->where(['isManager', 'isAdmin'], Criteria::LOGICAL_OR)
            ->find();

        foreach ($users as $user) {
            if ($user->isEditSelfExclusive()) {
                continue;
            }
            $personIds[] = (int) $user->getId();
        }

        return array_values(array_unique($personIds));
    }

    /**
     * The single coordinator a volunteer's mail should reply to (design §3.6): the team
     * leader when the schedule has a team, otherwise the ministry coordinator, otherwise
     * null — in which case #9710 simply does not set a Reply-To and replies fall back to
     * the church address, i.e. exactly today's behaviour.
     *
     * Ordering is by GrantedDate then Id so the choice is deterministic when several
     * people hold the same scope. Nothing else may re-derive this.
     */
    public function getReplyToPersonId(int $ministryId, ?int $teamId = null): ?int
    {
        if ($teamId !== null) {
            $teamScopes = $this->findScopeRows(self::SCOPE_TEAM, $teamId);
            if ($teamScopes !== []) {
                return (int) $teamScopes[0]->getPersonId();
            }
        }

        $ministryScopes = $this->findScopeRows(self::SCOPE_MINISTRY, $ministryId);
        if ($ministryScopes !== []) {
            return (int) $ministryScopes[0]->getPersonId();
        }

        return null;
    }

    /**
     * Scope rows matching any combination of person / ministry / team.
     *
     * @return VolunteerScope[]
     */
    public function listScopes(?int $personId = null, ?int $ministryId = null, ?int $teamId = null): array
    {
        $query = VolunteerScopeQuery::create();

        if ($personId !== null) {
            $query->filterByPersonId($personId);
        }

        if ($ministryId !== null && $teamId !== null) {
            // Both filters on one polymorphic column would be mutually exclusive and
            // always return nothing; the caller asked a question with no answer.
            return [];
        }

        if ($ministryId !== null) {
            $query->filterByScopeType(self::SCOPE_MINISTRY)->filterByScopeId($ministryId);
        }

        if ($teamId !== null) {
            $query->filterByScopeType(self::SCOPE_TEAM)->filterByScopeId($teamId);
        }

        return iterator_to_array(
            $query->orderByGrantedDate()->orderById()->find(),
            false
        );
    }

    // ── Grant / revoke ─────────────────────────────────────────────────────

    /**
     * Grant coordinator or team-leader authority. Idempotent: `vscp_person_scope_uidx`
     * makes (person, type, target) unique, so a repeat grant returns the existing row
     * rather than creating a second one or failing with a 409 (§6.6).
     *
     * The caller is responsible for having authorized the grant and for having checked
     * that the target exists — the column is polymorphic and carries no foreign key
     * (§2.15), so the database cannot check it.
     */
    public function grantScope(int $personId, string $scopeType, int $scopeId, int $grantedByPersonId): VolunteerScope
    {
        $scope = VolunteerScopeQuery::create()
            ->filterByPersonId($personId)
            ->filterByScopeType($scopeType)
            ->filterByScopeId($scopeId)
            ->findOneOrCreate();

        if ($scope->isNew()) {
            // Naive wall-clock in sTimeZone, matching every other V2 timestamp (§2.0).
            $scope->setGrantedDate(DateTimeUtils::getNowDateTime());
            $scope->setGrantedByPersonId($grantedByPersonId > 0 ? $grantedByPersonId : null);
            $scope->save();

            $this->logger->info('Volunteer scope granted', [
                'personId' => $personId,
                'scopeType' => $scopeType,
                'scopeId' => $scopeId,
                'grantedBy' => $grantedByPersonId,
            ]);
        }

        $this->forgetScopes($personId);

        return $scope;
    }

    public function revokeScope(VolunteerScope $scope): void
    {
        $personId = (int) $scope->getPersonId();

        $this->logger->info('Volunteer scope revoked', [
            'scopeId' => $scope->getId(),
            'personId' => $personId,
            'scopeType' => $scope->getScopeType(),
            'scopeId' => $scope->getScopeId(),
        ]);

        $scope->delete();
        $this->forgetScopes($personId);
    }

    /**
     * Does the polymorphic target of a scope row actually exist? There is no foreign key
     * to enforce it (§2.15), so this is the only integrity check there is.
     */
    public function scopeTargetExists(string $scopeType, int $scopeId): bool
    {
        if ($scopeType === self::SCOPE_TEAM) {
            return VolunteerTeamQuery::create()->findPk($scopeId) !== null;
        }

        return VolunteerMinistryQuery::create()->findPk($scopeId) !== null;
    }

    public function personExists(int $personId): bool
    {
        return PersonQuery::create()->findPk($personId) !== null;
    }

    // ── Internals ──────────────────────────────────────────────────────────

    /**
     * @return VolunteerScope[] oldest grant first, so callers get a deterministic pick
     */
    private function findScopeRows(string $scopeType, int $scopeId): array
    {
        return iterator_to_array(
            VolunteerScopeQuery::create()
                ->filterByScopeType($scopeType)
                ->filterByScopeId($scopeId)
                ->orderByGrantedDate()
                ->orderById()
                ->find(),
            false
        );
    }

    /**
     * The raw grants held by one user, memoised for the request.
     *
     * Short-circuits to no grants when V2 is switched off or the user is EditSelf-exclusive:
     * the volunteer persona is never a coordinator (§4.3), and a rolled-back installation
     * grants nothing. Both checks are cheap and keep every predicate consistent without
     * repeating themselves.
     *
     * @return array{ministry: int[], team: int[]}
     */
    private function loadScopes(User $user): array
    {
        $personId = (int) $user->getId();

        if (isset($this->scopeCache[$personId])) {
            return $this->scopeCache[$personId];
        }

        if (!User::isVolunteerV2Enabled() || $user->isEditSelfExclusive()) {
            return $this->scopeCache[$personId] = ['ministry' => [], 'team' => []];
        }

        $result = ['ministry' => [], 'team' => []];
        foreach (VolunteerScopeQuery::create()->filterByPersonId($personId)->find() as $scope) {
            $key = $scope->getScopeType() === self::SCOPE_TEAM ? 'team' : 'ministry';
            $result[$key][] = (int) $scope->getScopeId();
        }

        return $this->scopeCache[$personId] = $result;
    }

    private function forgetScopes(int $personId): void
    {
        unset($this->scopeCache[$personId], self::$manageableMinistryMemo[$personId]);
    }
}
