<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;

/**
 * Photo book: for each class, its teachers and then its students, with their photos.
 */
class PhotoBookService
{
    private const ROLES = ['Teacher', 'Student'];

    /**
     * @param int[] $groupIds
     * @return array<int, array{group: string, role: string, people: array<int, array{name: string, photo: string}>}>
     */
    public static function sections(array $groupIds): array
    {
        $sections = [];
        foreach ($groupIds as $groupId) {
            $group = GroupQuery::create()->findOneById($groupId);
            if ($group === null) {
                continue;
            }

            foreach (self::ROLES as $roleName) {
                $role = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionName($roleName)->findOne();
                if ($role === null) {
                    continue;
                }

                $memberships = Person2group2roleP2g2rQuery::create()
                    ->filterByGroup($group)
                    ->filterByRoleId($role->getOptionId())
                    ->joinWithPerson()
                    ->usePersonQuery()
                        ->orderByLastName()
                        ->orderByFirstName()
                    ->endUse()
                    ->find();

                $people = [];
                foreach ($memberships as $membership) {
                    $person = $membership->getPerson();
                    $photo = (string) $person->getPhoto()->getPhotoURI();
                    $people[] = ['name' => $person->getFullName(), 'photo' => is_file($photo) ? $photo : ''];
                }

                $sections[] = ['group' => (string) $group->getName(), 'role' => $roleName === 'Teacher' ? gettext('Teachers') : gettext('Students'), 'people' => $people];
            }
        }

        return $sections;
    }
}
