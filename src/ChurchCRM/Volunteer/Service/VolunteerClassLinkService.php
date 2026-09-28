<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\VolunteerException;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * A team linked to a Sunday School class (D23, design §2.4).
 *
 * Core keeps the class: its roster, its Student role, its attendance and every
 * Sunday School read. The link hands V2 one thing — who holds the class's
 * **Teacher** role. A qualification for any position of the linked team makes a
 * person a teacher, and their last one ending stops it, so teachers are entered
 * once, on the Volunteers grid.
 *
 * Membership writes here run inside `VolunteerPoolWriter::run()`, D19's
 * managed-write context, and only after the calling service has authorized the
 * qualification change. While V2 is on, the model hooks refuse the same
 * Teacher-role write from anywhere else (`findTeacherWriteConflict()`).
 */
final class VolunteerClassLinkService
{
    public const SUNDAY_SCHOOL_GROUP_TYPE = 4;

    /** Core reads teachers by this role NAME (SundaySchoolService, ClassAttendance, the class page). */
    public const TEACHER_ROLE_NAME = 'Teacher';

    /**
     * D29: only a ministry that provides teachers for Sunday School may link a team to a
     * class, follow a class's meetings or give its events a class.
     *
     * @throws VolunteerException 400
     */
    public static function assertMinistryTeaches(int $ministryId): void
    {
        $ministry = VolunteerMinistryQuery::create()->findPk($ministryId);
        if ($ministry === null || $ministry->getSundaySchool()) {
            return;
        }

        throw VolunteerException::invalid(sprintf(
            gettext('%s does not provide teachers for Sunday School, so its teams, schedules and events cannot use a class. A volunteer manager can change that under Edit ministry.'),
            $ministry->getName()
        ));
    }

    public static function findLinkedTeam(int $groupId): ?VolunteerTeam
    {
        return VolunteerTeamQuery::create()->findOneByClassGroupId($groupId);
    }

    /** `lst_OptionID` of the role named Teacher in the class's role list, or null. */
    public static function teacherRoleId(Group $group): ?int
    {
        $option = ListOptionQuery::create()
            ->filterById((int) $group->getRoleListId())
            ->filterByOptionName(self::TEACHER_ROLE_NAME)
            ->findOne();

        return $option === null ? null : (int) $option->getOptionId();
    }

    /**
     * D23 (c): the refusal for a membership write that would add, remove, or change
     * a role to or from Teacher on a linked class, when it does not come from V2.
     * `null` role means "not a member". Returns null when the write is allowed.
     *
     * Free for every installation with the rollout at `v1`: no query runs before
     * the rollout check.
     */
    public static function findTeacherWriteConflict(int $groupId, ?int $fromRoleId, ?int $toRoleId): ?VolunteerException
    {
        if ($fromRoleId === $toRoleId || VolunteerPoolWriter::isManagedWriteOpen() || !User::isVolunteerV2Enabled()) {
            return null;
        }

        $team = self::findLinkedTeam($groupId);
        $group = $team?->getClassGroup();
        if ($team === null || $group === null) {
            return null;
        }

        $teacherRoleId = self::teacherRoleId($group);
        if ($teacherRoleId === null || ($fromRoleId !== $teacherRoleId && $toRoleId !== $teacherRoleId)) {
            return null;
        }

        return self::managedElsewhere(
            $team,
            $group,
            gettext('The teachers of %1$s are managed in Ministries → %2$s → %3$s. Students can still be changed here.')
        );
    }

    /**
     * Renaming or deleting the Teacher role would un-teacher the whole class
     * behind V2's back, so it is refused like a Teacher-role membership write.
     */
    public static function findTeacherRoleLock(int $groupId, int $roleId): ?VolunteerException
    {
        if (!User::isVolunteerV2Enabled()) {
            return null;
        }

        $team = self::findLinkedTeam($groupId);
        $group = $team?->getClassGroup();
        if ($team === null || $group === null || self::teacherRoleId($group) !== $roleId) {
            return null;
        }

        return self::managedElsewhere(
            $team,
            $group,
            gettext('The Teacher role of %1$s cannot be renamed or deleted while its teachers are managed in Ministries → %2$s → %3$s.')
        );
    }

    /** A linked class must stay a Sunday School class; unlinking comes first. */
    public static function findClassTypeLock(int $groupId): ?VolunteerException
    {
        if (!User::isVolunteerV2Enabled()) {
            return null;
        }

        $team = self::findLinkedTeam($groupId);
        $group = $team?->getClassGroup();
        if ($team === null || $group === null) {
            return null;
        }

        return self::managedElsewhere(
            $team,
            $group,
            gettext('%1$s stays a Sunday School class while its teachers are managed in Ministries → %2$s → %3$s. Unlink it from the team first.')
        );
    }

    private static function managedElsewhere(VolunteerTeam $team, Group $group, string $format): VolunteerException
    {
        $ministry = $team->getMinistry();
        $ministryName = $ministry === null ? '' : (string) $ministry->getName();

        return VolunteerException::conflict(sprintf($format, $group->getName(), $ministryName, $team->getName()))
            ->withExtra([
                'ministryId' => (int) $team->getMinistryId(),
                'ministryName' => $ministryName,
                'teamId' => (int) $team->getId(),
                'teamName' => (string) $team->getName(),
            ]);
    }

    /**
     * What the class page and the group view say about a link (D23): null unless
     * V2 is on and a team is linked. `canOpenMinistry` decides whether the ministry
     * is offered as a link or named as plain text.
     *
     * @return array{ministryId: int, ministryName: string, teamId: int, teamName: string, teacherRoleId: int|null, canOpenMinistry: bool}|null
     */
    public static function describeLink(int $groupId, User $viewer): ?array
    {
        if (!User::isVolunteerV2Enabled()) {
            return null;
        }

        $team = self::findLinkedTeam($groupId);
        $group = $team?->getClassGroup();
        if ($team === null || $group === null) {
            return null;
        }

        $ministryId = (int) $team->getMinistryId();
        $ministry = $team->getMinistry();

        return [
            'ministryId' => $ministryId,
            'ministryName' => $ministry === null ? '' : (string) $ministry->getName(),
            'teamId' => (int) $team->getId(),
            'teamName' => (string) $team->getName(),
            'teacherRoleId' => self::teacherRoleId($group),
            'canOpenMinistry' => $viewer->isVolunteerCoordinatorEnabled()
                && (new VolunteerAuthorizationService())->canManageMinistry($viewer, $ministryId),
        ];
    }

    /**
     * The Sunday School classes a team may link: every type-4 group not linked to
     * another team, the one `$teamId` already holds included, each with how many
     * teachers linking it would import.
     *
     * @return array<int, array{id: int, name: string, teacherCount: int}>
     */
    public function listLinkableClasses(?int $teamId = null): array
    {
        $linkedTo = [];
        $rows = VolunteerTeamQuery::create()
            ->filterByClassGroupId(null, Criteria::ISNOTNULL)
            ->select(['Id', 'ClassGroupId'])
            ->find();
        foreach ($rows as $row) {
            $linkedTo[(int) $row['ClassGroupId']] = (int) $row['Id'];
        }

        $classes = [];
        foreach (GroupQuery::create()->filterByType(self::SUNDAY_SCHOOL_GROUP_TYPE)->orderByName()->find() as $group) {
            $linkedTeamId = $linkedTo[(int) $group->getId()] ?? null;
            if ($linkedTeamId === null || $linkedTeamId === $teamId) {
                $classes[] = $group;
            }
        }

        $counts = $this->countTeachers($classes);

        return array_map(
            static fn (Group $group): array => [
                'id' => (int) $group->getId(),
                'name' => (string) $group->getName(),
                'teacherCount' => $counts[(int) $group->getId()] ?? 0,
            ],
            $classes
        );
    }

    /**
     * @throws VolunteerException 400 unless it is a Sunday School class with a Teacher
     *                            role, 409 when another team already holds it
     */
    public function requireLinkableClass(int $groupId, ?int $teamId): Group
    {
        $group = GroupQuery::create()->findPk($groupId);
        if ($group === null || (int) $group->getType() !== self::SUNDAY_SCHOOL_GROUP_TYPE) {
            throw VolunteerException::invalid(gettext('Only a Sunday School class can be linked to a team'));
        }

        $holder = self::findLinkedTeam($groupId);
        if ($holder !== null && (int) $holder->getId() !== $teamId) {
            $ministry = $holder->getMinistry();

            throw VolunteerException::conflict(sprintf(
                gettext('%1$s is already linked to %2$s in %3$s'),
                $group->getName(),
                $holder->getName(),
                $ministry === null ? '' : $ministry->getName()
            ));
        }

        if (self::teacherRoleId($group) === null) {
            throw VolunteerException::invalid(sprintf(
                gettext('%s has no role named Teacher, so its teachers cannot be managed from a team'),
                $group->getName()
            ));
        }

        return $group;
    }

    /**
     * The people holding the class's Teacher role now — who linking imports.
     * Deceased people are left out, as the class page leaves them out.
     *
     * @return int[]
     */
    public function teacherPersonIds(Group $group): array
    {
        $teacherRoleId = self::teacherRoleId($group);
        if ($teacherRoleId === null) {
            return [];
        }

        $ids = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId((int) $group->getId())
            ->filterByRoleId($teacherRoleId)
            ->usePersonQuery()
                ->filterByDateDeceased(null, Criteria::ISNULL)
            ->endUse()
            ->select(['PersonId'])
            ->find()
            ->toArray();

        return array_map('intval', $ids);
    }

    /**
     * A grant would make this person a teacher. Somebody already in the class under
     * another role — a Student above all — is refused rather than silently re-roled.
     *
     * @throws VolunteerException 409
     */
    public function assertMayTeach(Group $group, int $personId): void
    {
        $membership = $this->findMembership($group, $personId);
        if ($membership === null || (int) $membership->getRoleId() === self::teacherRoleId($group)) {
            return;
        }

        $role = ListOptionQuery::create()
            ->filterById((int) $group->getRoleListId())
            ->filterByOptionId((int) $membership->getRoleId())
            ->findOne();
        $person = PersonQuery::create()->findPk($personId);

        throw VolunteerException::conflict(sprintf(
            gettext('%1$s is in %2$s as %3$s. Remove them from the class first; qualifying them here then makes them one of its teachers.'),
            $person === null ? '' : $person->getFullName(),
            $group->getName(),
            $role === null ? '' : $role->getOptionName()
        ))->withExtra(['classGroupId' => (int) $group->getId()]);
    }

    /**
     * Give the person the class's Teacher role; nothing happens when they have it.
     * The caller has authorized the qualification behind it and called
     * `assertMayTeach()`. Returns true when a membership row was created.
     */
    public function addTeacher(Group $group, int $personId): bool
    {
        $teacherRoleId = self::teacherRoleId($group);
        if ($teacherRoleId === null || $this->findMembership($group, $personId) !== null) {
            return false;
        }

        VolunteerPoolWriter::run(function () use ($group, $personId, $teacherRoleId): void {
            $membership = new Person2group2roleP2g2r();
            $membership->setGroupId((int) $group->getId());
            $membership->setPersonId($personId);
            $membership->setRoleId($teacherRoleId);
            $membership->save();

            HookManager::doAction(Hooks::GROUP_MEMBER_ADDED, $membership, $group, PersonQuery::create()->findPk($personId));
        });

        LoggerUtils::getAppLogger()->info('Volunteer class teacher added', [
            'groupId' => $group->getId(),
            'personId' => $personId,
        ]);

        return true;
    }

    /**
     * After a revocation or a position deletion: once the person holds no active
     * qualification for any position of the team, their class membership ends —
     * if, and only if, its role is Teacher. Returns true when it was removed.
     */
    public function removeTeacherIfUnqualified(VolunteerTeam $team, Group $group, int $personId): bool
    {
        $remaining = VolunteerQualificationQuery::create()
            ->filterByPersonId($personId)
            ->filterByActive(true)
            ->usePositionQuery()
                ->filterByTeamId((int) $team->getId())
            ->endUse()
            ->count();

        $membership = $remaining > 0 ? null : $this->findMembership($group, $personId);
        if ($membership === null || (int) $membership->getRoleId() !== self::teacherRoleId($group)) {
            return false;
        }

        VolunteerPoolWriter::run(static function () use ($membership, $group, $personId): void {
            $membership->delete();
            HookManager::doAction(Hooks::GROUP_MEMBER_REMOVED, $personId, $group);
        });

        LoggerUtils::getAppLogger()->info('Volunteer class teacher removed', [
            'groupId' => $group->getId(),
            'teamId' => $team->getId(),
            'personId' => $personId,
        ]);

        return true;
    }

    private function findMembership(Group $group, int $personId): ?Person2group2roleP2g2r
    {
        return Person2group2roleP2g2rQuery::create()
            ->filterByGroupId((int) $group->getId())
            ->filterByPersonId($personId)
            ->findOne();
    }

    /**
     * Living Teacher-role members per class, in two queries whatever the number of classes.
     *
     * @param Group[] $groups
     *
     * @return array<int, int> group id → teacher count
     */
    private function countTeachers(array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        $roleListOf = [];
        foreach ($groups as $group) {
            $roleListOf[(int) $group->getId()] = (int) $group->getRoleListId();
        }

        $teacherOptionOf = [];
        $options = ListOptionQuery::create()
            ->filterById(array_values($roleListOf), Criteria::IN)
            ->filterByOptionName(self::TEACHER_ROLE_NAME)
            ->find();
        foreach ($options as $option) {
            $teacherOptionOf[(int) $option->getId()] = (int) $option->getOptionId();
        }

        $counts = [];
        $rows = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId(array_keys($roleListOf), Criteria::IN)
            ->usePersonQuery()
                ->filterByDateDeceased(null, Criteria::ISNULL)
            ->endUse()
            ->select(['GroupId', 'RoleId'])
            ->find();
        foreach ($rows as $row) {
            $groupId = (int) $row['GroupId'];
            if (($teacherOptionOf[$roleListOf[$groupId]] ?? null) === (int) $row['RoleId']) {
                $counts[$groupId] = ($counts[$groupId] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
