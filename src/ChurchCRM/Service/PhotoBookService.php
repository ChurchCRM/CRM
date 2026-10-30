<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Bootstrapper;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;

/**
 * Photo book: for each class, its teachers and then its students, with their photos.
 */
class PhotoBookService
{
    public const FRAME_WIDTH_MM = 38;
    public const FRAME_HEIGHT_MM = 46;

    private const ROLES = ['Teacher', 'Student'];

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
                    $people[] = self::describe($membership->getPerson(), $roleName === 'Teacher');
                }

                $sections[] = ['group_id' => (int) $group->getId(), 'group' => (string) $group->getName(), 'role' => $roleName === 'Teacher' ? gettext('Teachers') : gettext('Students'), 'people' => $people];
            }
        }

        return self::sizeBlanksLikePhotos($sections);
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
            'initials' => mb_strtoupper(mb_substr((string) $person->getFirstName(), 0, 1) . mb_substr((string) $person->getLastName(), 0, 1)),
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
