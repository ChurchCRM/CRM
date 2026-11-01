<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\Utils\DateTimeUtils;

/**
 * Class list: for each class, its teachers and liaisons, then its students with their family contact details.
 */
class ClassListService
{
    /**
     * @param int[] $groupIds
     * @return array<int, array{name: string, teachers: string[], liaisons: string[], students: array<int, array<string, mixed>>}>
     */
    public static function classes(array $groupIds): array
    {
        $classes = [];
        foreach ($groupIds as $groupId) {
            $group = GroupQuery::create()->findOneById($groupId);
            if ($group === null) {
                continue;
            }

            $roleNames = [];
            $options = ListOptionQuery::create()->filterById($group->getRoleListId())->find();
            foreach ($options as $option) {
                $roleNames[$option->getOptionId()] = $option->getOptionName();
            }

            $class = ['name' => (string) $group->getName(), 'teachers' => [], 'liaisons' => [], 'students' => []];

            $memberships = Person2group2roleP2g2rQuery::create()
                ->filterByGroup($group)
                ->joinWithPerson()
                ->usePersonQuery()
                    ->orderByLastName()
                    ->orderByFirstName()
                ->endUse()
                ->find();

            foreach ($memberships as $membership) {
                $roleName = $roleNames[$membership->getRoleId()] ?? null;
                $person = $membership->getPerson();
                $family = $person->getFamily();

                if ($roleName === 'Teacher' || $roleName === 'Liaison') {
                    $entry = trim($person->getFullName() . ' ' . ($family?->getHomePhone() ?? ''));
                    $class[$roleName === 'Teacher' ? 'teachers' : 'liaisons'][] = $entry;
                } elseif ($roleName === 'Student') {
                    $portrait = ReportPhoto::forPerson($person);
                    $class['students'][] = [
                        'name' => $person->getFullName(),
                        'birthday' => DateTimeUtils::formatForDatePicker($person->getBirthYear() . '-' . $person->getBirthMonth() . '-' . $person->getBirthDay()),
                        'initials' => $portrait['initials'],
                        'photo' => $portrait['photo'],
                        'properties' => $person->getPropertiesString(),
                        'parents' => $family === null ? '' : (string) $family->getSalutation(),
                        'phone' => $family === null ? '' : (string) $family->getHomePhone(),
                        'address' => $family === null ? '' : self::address($family),
                    ];
                }
            }

            $classes[] = $class;
        }

        return $classes;
    }

    private static function address(Family $family): string
    {
        $street = trim($family->getAddress1() . ' ' . $family->getAddress2());
        $region = trim($family->getState() . ' ' . $family->getZip());

        return implode(', ', array_filter([$street, (string) $family->getCity(), $region]));
    }
}
