<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
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

        $classInfo = [];
        foreach ($groups as $group) {
            $groupId = $group->getId();
            $roleListId = $group->getRoleListId();

            // Count teachers and students for this group by looking up role
            // names in list_lst.
            $teachers = 0;
            $kids = 0;

            if ($roleListId !== null) {
                // Get all role assignments for this group
                $roleAssignments = Person2group2roleP2g2rQuery::create()
                    ->filterByGroupId($groupId)
                    ->find();

                foreach ($roleAssignments as $assignment) {
                    $roleId = $assignment->getRoleId();

                    // Look up role name from list_lst
                    $roleOption = ListOptionQuery::create()
                        ->filterById($roleListId)
                        ->filterByOptionId($roleId)
                        ->findOne();

                    if ($roleOption !== null) {
                        $roleName = $roleOption->getOptionName();
                        if ($roleName === 'Teacher') {
                            $teachers++;
                        } elseif ($roleName === 'Student') {
                            $kids++;
                        }
                    }
                }
            }

            $classInfo[] = [
                'id'       => $groupId,
                'name'     => $group->getName(),
                'teachers' => $teachers,
                'kids'     => $kids,
            ];
        }

        return $classInfo;
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
            ->filterByGroupId((int) $groupId)
            ->filterByRoleId($roleOption->getOptionId())
            ->joinWithPerson()
            ->find();

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

        return ['Boys' => $boys, 'Girls' => $girls, 'Unknown' => $unknown];
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
            ->filterByGroupId((int) $groupId)
            ->filterByRoleId($roleOption->getOptionId())
            ->joinWithPerson()
            ->orderBy(PersonTableMap::COL_PER_LASTNAME)
            ->_and()
            ->orderBy(PersonTableMap::COL_PER_FIRSTNAME)
            ->find();

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
                'sundayschoolClass' => $group->getName(),
                'kidId'       => $kid->getId(),
                'kidGender'   => $kid->getGender(),
                'firstName'   => $kid->getFirstName(),
                'kidEmail'    => $kid->getEmail(),
                'LastName'    => $kid->getLastName(),
                'birthDay'    => $kid->getBirthDay(),
                'birthMonth'  => $kid->getBirthMonth(),
                'birthYear'   => $kid->getBirthYear(),
                'mobilePhone' => $kid->getCellPhone(),
                'flags'       => $kid->getFlags(),
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
                'famEmail'    => $fam?->getEmail(),
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
        // Get all person IDs already enrolled as students (role ID 2) in any Sunday School group
        $enrolledPersonIds = Person2group2roleP2g2rQuery::create()
            ->useGroupQuery()
                ->filterByType(4)
            ->endUse()
            ->filterByRoleId(2)
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
                'firstName'   => $kid->getFirstName(),
                'LastName'    => $kid->getLastName(),
                'birthDay'    => $kid->getBirthDay(),
                'birthMonth'  => $kid->getBirthMonth(),
                'birthYear'   => $kid->getBirthYear(),
                'mobilePhone' => $kid->getCellPhone(),
                'flags'       => $kid->getFlags(),
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
