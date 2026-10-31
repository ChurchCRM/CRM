<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\Bootstrapper;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;

/**
 * Photo book: for each group, its members with their photos. A Sunday School class lists its
 * teachers and then its students; any other group lists each role in list order.
 */
class PhotoBookService
{
    public const FRAME_WIDTH_MM = 38;
    public const FRAME_HEIGHT_MM = 46;

    private const CLASS_ROLES = ['Teacher', 'Student'];

    /**
     * @param int[] $groupIds
     * @return array<int, array{group_id: int, group: string, role: string, people: array<int, array{name: string, photo: string, photo_width: float, photo_height: float, initials: string, details: string[]}>}>
     */
    public static function sections(array $groupIds): array
    {
        $sections = [];
        foreach ($groupIds as $groupId) {
            $group = GroupQuery::create()->findOneById($groupId);
            if ($group === null) {
                continue;
            }

            foreach (self::rolesOf($group) as $role) {
                $roleName = (string) $role->getOptionName();
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
                    $people[] = self::describe($membership->getPerson(), !$group->isSundaySchool() || $roleName === 'Teacher');
                }

                $sections[] = ['group_id' => (int) $group->getId(), 'group' => (string) $group->getName(), 'role' => $group->isSundaySchool() ? ($roleName === 'Teacher' ? gettext('Teachers') : gettext('Students')) : $roleName, 'people' => $people];
            }
        }

        return self::sizeBlanksLikePhotos($sections);
    }

    /** @return ListOption[] */
    private static function rolesOf(Group $group): array
    {
        $options = ListOptionQuery::create()->filterById($group->getRoleListId())->orderByOptionSequence()->find()->getArrayCopy();
        if (!$group->isSundaySchool()) {
            return $options;
        }

        $byName = [];
        foreach ($options as $option) {
            $byName[$option->getOptionName()] = $option;
        }

        return array_values(array_filter(array_map(fn (string $name): ?ListOption => $byName[$name] ?? null, self::CLASS_ROLES)));
    }

    /** A person without a photo gets a blank the size of the first photo in the book. */
    private static function sizeBlanksLikePhotos(array $sections): array
    {
        $width = self::FRAME_WIDTH_MM;
        $height = self::FRAME_HEIGHT_MM;
        foreach ($sections as $section) {
            foreach ($section['people'] as $person) {
                if ($person['photo'] !== '') {
                    $width = $person['photo_width'];
                    $height = $person['photo_height'];
                    break 2;
                }
            }
        }

        foreach ($sections as &$section) {
            foreach ($section['people'] as &$person) {
                if ($person['photo'] === '') {
                    $person['photo_width'] = $width;
                    $person['photo_height'] = $height;
                }
            }
            unset($person);
        }
        unset($section);

        return $sections;
    }

    private static function describe(Person $person, bool $isTeacher): array
    {
        $photo = (string) $person->getPhoto()->getPhotoURI();
        $photo = is_file($photo) ? $photo : '';
        [$width, $height] = self::fitPhoto($photo);

        $details = [];
        if ($isTeacher) {
            $phone = $person->getCellPhone() ?: $person->getFamilyPhone(0);
            if ($phone !== '') {
                $details[] = $phone;
            }
        } else {
            $facts = [];
            if (!$person->hideAge() && $person->getNumericAge() >= 0) {
                $facts[] = sprintf(gettext('Age %d'), $person->getNumericAge());
            }
            $birthday = self::birthday($person);
            if ($birthday !== '') {
                $facts[] = $birthday;
            }
            if ($facts !== []) {
                $details[] = implode(' · ', $facts);
            }

            $adults = $person->getFamily() ? $person->getFamily()->getAdults() : [];
            if ($adults !== []) {
                $details[] = implode(' & ', array_map(fn (Person $adult): string => (string) $adult->getFirstName(), $adults));
                $phone = $adults[0]->getCellPhone() ?: $person->getFamilyPhone(0);
                if ($phone !== '') {
                    $details[] = $phone;
                }
            }
        }

        return [
            'name' => $person->getFullName(),
            'photo' => $photo,
            'photo_width' => $width,
            'photo_height' => $height,
            'initials' => $person->getInitial(SystemConfig::getIntValue('iPersonInitialStyle')),
            'details' => $details,
        ];
    }

    private static function birthday(Person $person): string
    {
        if (empty($person->getBirthMonth()) || empty($person->getBirthDay())) {
            return '';
        }
        $date = new \DateTimeImmutable(sprintf('2000-%02d-%02d', (int) $person->getBirthMonth(), (int) $person->getBirthDay()));
        if (!class_exists(\IntlDateFormatter::class)) {
            return $date->format('M j');
        }

        return (string) \IntlDateFormatter::formatObject($date, 'MMM d', Bootstrapper::getCurrentLocale()->getLocale());
    }

    /** Photo size in mm that fits the frame while keeping the aspect ratio, so every frame is the same size. */
    private static function fitPhoto(string $photo): array
    {
        $size = $photo === '' ? false : @getimagesize($photo);
        if (!$size || $size[0] < 1 || $size[1] < 1) {
            return [self::FRAME_WIDTH_MM, self::FRAME_HEIGHT_MM];
        }
        $scale = min(self::FRAME_WIDTH_MM / $size[0], self::FRAME_HEIGHT_MM / $size[1]);

        return [round($size[0] * $scale, 1), round($size[1] * $scale, 1)];
    }
}
