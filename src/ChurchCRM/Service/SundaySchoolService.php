<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class SundaySchoolService
{
    /**
     * Get statistics for all Sunday School classes (type 4), including classes
     * with zero members.
     *
     * @return array<int, array{id: int, name: string, teachers: int, kids: int}>
     */
    public function getClassStats(): array
    {
        // First, get all Sunday School groups (type 4). This guarantees we
        // include classes with zero members.
        $groups = GroupQuery::create()
            ->filterByType(4)
            ->orderByName(Criteria::ASC)
            ->find();

        // Collect all role list IDs, then batch-fetch their role options in one query
        $roleListIds = [];
        foreach ($groups as $group) {
            if ($group->getRoleListId() !== null) {
                $roleListIds[] = $group->getRoleListId();
            }
        }
        $roleListIds = array_unique($roleListIds);

        // Build a lookup: [listId][optionId] => roleName
        $roleNameLookup = [];
        if (!empty($roleListIds)) {
            $allRoleOptions = ListOptionQuery::create()
                ->filterById($roleListIds, Criteria::IN)
                ->find();
            foreach ($allRoleOptions as $option) {
                $roleNameLookup[$option->getId()][$option->getOptionId()] = $option->getOptionName();
            }
        }

        // Batch-fetch all role assignments for all SS groups in one query
        $groupIds = [];
        foreach ($groups as $group) {
            $groupIds[] = $group->getId();
        }

        // [groupId] => ['teachers' => int, 'kids' => int]
        $groupCounts = [];
        if (!empty($groupIds)) {
            $allAssignments = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId($groupIds, Criteria::IN)
                ->find();

            foreach ($allAssignments as $assignment) {
                $gid = $assignment->getGroupId();
                $roleId = $assignment->getRoleId();

                // Find the role list for this group
                $group = null;
                foreach ($groups as $g) {
                    if ($g->getId() === $gid) {
                        $group = $g;
                        break;
                    }
                }
                if ($group === null || $group->getRoleListId() === null) {
                    continue;
                }

                $listId = $group->getRoleListId();
                $roleName = $roleNameLookup[$listId][$roleId] ?? null;

                if ($roleName === 'Teacher') {
                    $groupCounts[$gid]['teachers'] = ($groupCounts[$gid]['teachers'] ?? 0) + 1;
                } elseif ($roleName === 'Student') {
                    $groupCounts[$gid]['kids'] = ($groupCounts[$gid]['kids'] ?? 0) + 1;
                }
            }
        }

        $classInfo = [];
        foreach ($groups as $group) {
            $gid = $group->getId();
            $classInfo[] = [
                'id'       => $gid,
                'name'     => $group->getName(),
                'teachers' => $groupCounts[$gid]['teachers'] ?? 0,
                'kids'     => $groupCounts[$gid]['kids'] ?? 0,
            ];
        }

        return $classInfo;
    }

    /**
     * Get aggregate gender/family stats across all Sunday School classes in a
     * single batch — avoids the N+1 pattern of calling getKidsFullDetails()
     * per class.
     *
     * @return array{maleKids: int, femaleKids: int, familyCount: int}
     */
    public function getDashboardStudentStats(): array
    {
        $groups = GroupQuery::create()
            ->filterByType(4)
            ->find();

        // Resolve all "Student" role option IDs across SS groups
        $roleListIds = [];
        foreach ($groups as $group) {
            if ($group->getRoleListId() !== null) {
                $roleListIds[] = $group->getRoleListId();
            }
        }
        $roleListIds = array_unique($roleListIds);

        $studentOptionIds = [];
        $roleListToStudentOption = [];
        if (!empty($roleListIds)) {
            $options = ListOptionQuery::create()
                ->filterById($roleListIds, Criteria::IN)
                ->filterByOptionName('Student')
                ->find();
            foreach ($options as $opt) {
                $studentOptionIds[] = $opt->getOptionId();
                $roleListToStudentOption[$opt->getId()] = $opt->getOptionId();
            }
        }

        if (empty($studentOptionIds)) {
            return ['maleKids' => 0, 'femaleKids' => 0, 'familyCount' => 0];
        }

        // Build group-to-studentRoleId map
        $groupStudentRole = [];
        foreach ($groups as $group) {
            $listId = $group->getRoleListId();
            if ($listId !== null && isset($roleListToStudentOption[$listId])) {
                $groupStudentRole[$group->getId()] = $roleListToStudentOption[$listId];
            }
        }

        // Single query: all student memberships across all SS groups, joined with Person
        $groupIds = array_keys($groupStudentRole);
        if (empty($groupIds)) {
            return ['maleKids' => 0, 'femaleKids' => 0, 'familyCount' => 0];
        }

        $memberships = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->filterByGroupId($groupIds, Criteria::IN)
            ->filterByRoleId(array_unique(array_values($groupStudentRole)), Criteria::IN)
            ->find();

        $maleKids = 0;
        $femaleKids = 0;
        $familyIds = [];
        $seenPersonIds = [];

        foreach ($memberships as $membership) {
            // Verify role matches expected student role for this group
            $gid = $membership->getGroupId();
            if (!isset($groupStudentRole[$gid]) || $membership->getRoleId() !== $groupStudentRole[$gid]) {
                continue;
            }

            $person = $membership->getPerson();
            $pid = $person->getId();

            // Deduplicate students enrolled in multiple classes
            if (isset($seenPersonIds[$pid])) {
                continue;
            }
            $seenPersonIds[$pid] = true;

            if ($person->getGender() === 1) {
                $maleKids++;
            } elseif ($person->getGender() === 2) {
                $femaleKids++;
            }

            if ($person->getFamId() !== null) {
                $familyIds[] = $person->getFamId();
            }
        }

        return [
            'maleKids'    => $maleKids,
            'femaleKids'  => $femaleKids,
            'familyCount' => count(array_unique($familyIds)),
        ];
    }

    /**
     * @return Person[]
     */
    public function getClassByRole(string $groupId, string $role): array
    {
        $group = GroupQuery::create()
            ->filterByType(4)
            ->findPk((int) $groupId);

        if ($group === null) {
            return [];
        }

        $roleOption = ListOptionQuery::create()
            ->filterById($group->getRoleListId())
            ->filterByOptionName($role)
            ->findOne();

        if ($roleOption === null) {
            return [];
        }

        $memberships = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->filterByRoleId($roleOption->getOptionId())
            ->findByGroupId((int) $groupId);

        $members = [];
        foreach ($memberships as $membership) {
            $members[] = $membership->getPerson();
        }

        return $members;
    }

    public function getKidsGender(string $groupId): array
    {
        $kids = $this->getClassByRole($groupId, 'Student');
        $boys = 0;
        $girls = 0;
        $unknown = 0;

        foreach ($kids as $kid) {
            switch ($kid->getGender()) {
                case 1:
                    $boys++;
                    break;
                case 2:
                    $girls++;
                    break;
                default:
                    $unknown++;
            }
        }

        return ['Male' => $boys, 'Female' => $girls, 'Other' => $unknown];
    }

    public function getKidsBirthdayMonth(string $groupId): array
    {
        $kids = $this->getClassByRole($groupId, 'Student');
        $Jan = 0;
        $Feb = 0;
        $Mar = 0;
        $Apr = 0;
        $May = 0;
        $June = 0;
        $July = 0;
        $Aug = 0;
        $Sept = 0;
        $Oct = 0;
        $Nov = 0;
        $Dec = 0;

        foreach ($kids as $kid) {
            switch ($kid->getBirthMonth()) {
                case 1:
                    $Jan++;
                    break;
                case 2:
                    $Feb++;
                    break;
                case 3:
                    $Mar++;
                    break;
                case 4:
                    $Apr++;
                    break;
                case 5:
                    $May++;
                    break;
                case 6:
                    $June++;
                    break;
                case 7:
                    $July++;
                    break;
                case 8:
                    $Aug++;
                    break;
                case 9:
                    $Sept++;
                    break;
                case 10:
                    $Oct++;
                    break;
                case 11:
                    $Nov++;
                    break;
                case 12:
                    $Dec++;
                    break;
            }
        }

        return ['Jan'   => $Jan,
            'Feb'       => $Feb,
            'Mar'       => $Mar,
            'Apr'       => $Apr,
            'May'       => $May,
            'June'      => $June,
            'July'      => $July,
            'Aug'       => $Aug,
            'Sept'      => $Sept,
            'Oct'       => $Oct,
            'Nov'       => $Nov,
            'Dec'       => $Dec,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getKidsFullDetails(string $groupId): array
    {
        $group = GroupQuery::create()
            ->filterByType(4)
            ->findPk((int) $groupId);

        if ($group === null) {
            return [];
        }

        $roleOption = ListOptionQuery::create()
            ->filterById($group->getRoleListId())
            ->filterByOptionName('Student')
            ->findOne();

        if ($roleOption === null) {
            return [];
        }

        $memberships = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->filterByRoleId($roleOption->getOptionId())
            ->addAscendingOrderByColumn('per_LastName')
            ->addAscendingOrderByColumn('per_FirstName')
            ->findByGroupId((int) $groupId);

        $kids = [];
        foreach ($memberships as $membership) {
            $kid = $membership->getPerson();
            $fam = $kid->getFamily();

            $dad = null;
            $mom = null;
            if ($fam !== null) {
                foreach ($fam->getAdults() as $adult) {
                    if ($adult->getGender() === 1 && $dad === null) {
                        $dad = $adult;
                    } elseif ($adult->getGender() === 2 && $mom === null) {
                        $mom = $adult;
                    }
                }
            }

            $kids[] = [
                'kidId'       => $kid->getId(),
                'kidGender'   => $kid->getGender(),
                'firstName'   => $kid->getFirstName(),
                'kidEmail'    => $kid->getEmail(),
                'LastName'    => $kid->getLastName(),
                'birthDay'    => $kid->getBirthDay(),
                'birthMonth'  => $kid->getBirthMonth(),
                'birthYear'   => $kid->getBirthYear(),
                'mobilePhone' => $kid->getCellPhone(),
                'hideAge'     => $kid->hideAge(),
                'homePhone'   => $fam?->getHomePhone(),
                'fam_id'      => $fam?->getId(),
                'dadId'       => $dad?->getId(),
                'dadFirstName'=> $dad?->getFirstName(),
                'dadLastName' => $dad?->getLastName(),
                'dadCellPhone'=> $dad?->getCellPhone(),
                'dadEmail'    => $dad?->getEmail(),
                'momId'       => $mom?->getId(),
                'momFirstName'=> $mom?->getFirstName(),
                'momLastName' => $mom?->getLastName(),
                'momCellPhone'=> $mom?->getCellPhone(),
                'momEmail'    => $mom?->getEmail(),
                'Address1'    => $fam?->getAddress1(),
                'Address2'    => $fam?->getAddress2(),
                'city'        => $fam?->getCity(),
                'state'       => $fam?->getState(),
                'zip'         => $fam?->getZip(),
            ];
        }

        return $kids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getKidsWithoutClasses(): array
    {
        // Dynamically resolve all "Student" role option IDs from the role lists
        // assigned to Sunday School groups (type 4).  Each group may define its
        // own role list, so we collect every distinct list ID first, then look up
        // the "Student" option within those lists.
        $sundaySchoolGroups = GroupQuery::create()
            ->filterByType(4)
            ->find();

        $roleListIds = [];
        foreach ($sundaySchoolGroups as $ssGroup) {
            if ($ssGroup->getRoleListId() !== null) {
                $roleListIds[] = $ssGroup->getRoleListId();
            }
        }
        $roleListIds = array_unique($roleListIds);

        $studentRoleIds = [2]; // fallback: used when no Sunday School groups exist or none have a 'Student' role option
        if (!empty($roleListIds)) {
            $resolved = ListOptionQuery::create()
                ->filterById($roleListIds, Criteria::IN)
                ->filterByOptionName('Student')
                ->select(['OptionId'])
                ->find()
                ->toArray();
            if (!empty($resolved)) {
                $studentRoleIds = array_unique($resolved);
            }
        }

        // Get all person IDs already enrolled as students in any Sunday School group
        $enrolledPersonIds = Person2group2roleP2g2rQuery::create()
            ->useGroupQuery()
                ->filterByType(4)
            ->endUse()
            ->filterByRoleId($studentRoleIds, Criteria::IN)
            ->select(['PersonId'])
            ->find()
            ->toArray();

        // Find children (family role = Child) with active classification in non-deactivated families
        // who are not enrolled in any Sunday School class
        $kidsQuery = PersonQuery::create()
            ->filterByClsId([1, 2], Criteria::IN)
            ->filterByFmrId(3)
            ->useFamilyQuery()
                ->filterByDateDeactivated(null, Criteria::ISNULL)
            ->endUse();

        if (!empty($enrolledPersonIds)) {
            $kidsQuery->filterById($enrolledPersonIds, Criteria::NOT_IN);
        }

        $kids = [];
        foreach ($kidsQuery->find() as $kid) {
            $fam = $kid->getFamily();
            $kids[] = [
                'kidId'       => $kid->getId(),
                'famId'       => $kid->getFamId(),
                'firstName'   => $kid->getFirstName(),
                'LastName'    => $kid->getLastName(),
                'birthDay'    => $kid->getBirthDay(),
                'birthMonth'  => $kid->getBirthMonth(),
                'birthYear'   => $kid->getBirthYear(),
                'mobilePhone' => $kid->getCellPhone(),
                'hideAge'     => $kid->hideAge(),
                'Address1'    => $fam?->getAddress1(),
                'Address2'    => $fam?->getAddress2(),
                'city'        => $fam?->getCity(),
                'state'       => $fam?->getState(),
                'zip'         => $fam?->getZip(),
            ];
        }

        return $kids;
    }
}
