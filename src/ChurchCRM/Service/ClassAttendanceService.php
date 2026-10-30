<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;

/**
 * Class attendance sheet: for each Sunday School class, a grid of its students and teachers
 * against the Sundays of the school year.
 */
class ClassAttendanceService
{
    public const MAX_SUNDAYS = 60;
    public const MAX_EXTRA_ROWS = 50;
    private const SUNDAY_SCHOOL_GROUP_TYPE = 4;
    private const PAGE_WIDTH_MM = 245;
    private const NAME_WIDTH_MM = 55;
    private const PHOTO_WIDTH_MM = 10;
    private const MAX_DAY_WIDTH_MM = 7;

    /**
     * The Sundays from $first to $last (inclusive, one week apart), each flagged when school is closed.
     *
     * @param string[] $noSchool dates as Y-m-d
     * @return array<int, array{day: string, month: string, closed: bool}>
     */
    public static function sundays(string $first, string $last, array $noSchool): array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $first);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $last);
        if ($date === false || $end === false) {
            return [];
        }

        $sundays = [];
        while ($date <= $end && count($sundays) < self::MAX_SUNDAYS) {
            $sundays[] = [
                'day' => $date->format('d'),
                'month' => mb_substr(gettext($date->format('F')), 0, 3),
                'closed' => in_array($date->format('Y-m-d'), $noSchool, true),
            ];
            $date = $date->modify('+7 days');
        }

        return $sundays;
    }

    /**
     * Month headings, each spanning its run of Sundays.
     *
     * @param array<int, array{month: string}> $sundays
     * @return array<int, array{label: string, span: int}>
     */
    public static function months(array $sundays): array
    {
        $months = [];
        $previous = null;
        foreach ($sundays as $sunday) {
            if ($sunday['month'] !== $previous) {
                $months[] = ['label' => $sunday['month'], 'span' => 0];
            }
            $months[count($months) - 1]['span']++;
            $previous = $sunday['month'];
        }

        return $months;
    }

    /**
     * Column widths in mm so the grid fits the page: the name column, the photo column, one column per Sunday.
     *
     * @return array{name: int, photo: int, day: float}
     */
    public static function columnWidths(int $sundayCount, bool $withPictures): array
    {
        $photo = $withPictures ? self::PHOTO_WIDTH_MM : 0;
        $room = self::PAGE_WIDTH_MM - self::NAME_WIDTH_MM - $photo;
        $day = $sundayCount > 0 ? min(self::MAX_DAY_WIDTH_MM, floor($room / $sundayCount * 10) / 10) : self::MAX_DAY_WIDTH_MM;

        return ['name' => self::NAME_WIDTH_MM, 'photo' => $photo, 'day' => $day];
    }

    /**
     * @param int[] $groupIds
     * @return array<int, array{name: string, teachers: string, liaisons: string, tables: array<int, array{title: string, rows: array, extra: int}>}>
     */
    public static function classes(array $groupIds, bool $allRoles, bool $withPictures, int $extraStudents, int $extraTeachers): array
    {
        $extraStudents = max(0, min(self::MAX_EXTRA_ROWS, $extraStudents));
        $extraTeachers = max(0, min(self::MAX_EXTRA_ROWS, $extraTeachers));

        $classes = [];
        foreach ($groupIds as $groupId) {
            $group = GroupQuery::create()->filterByType(self::SUNDAY_SCHOOL_GROUP_TYPE)->findOneById($groupId);
            if ($group === null) {
                continue;
            }

            $roleNames = [];
            foreach (ListOptionQuery::create()->filterById($group->getRoleListId())->find() as $option) {
                $roleNames[$option->getOptionId()] = $option->getOptionName();
            }

            $memberships = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId($group->getId())
                ->joinWithPerson()
                ->usePersonQuery()
                    ->orderByLastName()
                    ->orderByFirstName()
                ->endUse()
                ->find();

            $students = [];
            $teachers = [];
            $everyone = [];
            $liaisons = [];
            foreach ($memberships as $membership) {
                $person = $membership->getPerson();
                $row = ['name' => $person->getFullName(), 'photo' => $withPictures ? self::photoFile($person) : ''];
                $everyone[] = $row;

                // Role option names are stored untranslated, so compare them to the stored names, never to gettext().
                $role = $roleNames[$membership->getRoleId()] ?? '';
                if ($role === 'Teacher') {
                    $teachers[] = $row;
                } elseif ($role === 'Student') {
                    $students[] = $row;
                } elseif ($role === 'Liaison') {
                    $liaisons[] = trim($person->getFullName() . ' ' . $person->getFamily()?->getHomePhone());
                }
            }

            if ($allRoles) {
                $tables = [['title' => gettext('All Members'), 'rows' => $everyone, 'extra' => $extraStudents + $extraTeachers]];
            } else {
                $tables = [
                    ['title' => gettext('Students'), 'rows' => $students, 'extra' => $extraStudents],
                    ['title' => gettext('Teachers'), 'rows' => $teachers, 'extra' => $extraTeachers],
                ];
            }

            $classes[] = [
                'name' => (string) $group->getName(),
                'teachers' => $allRoles ? '' : implode(', ', array_column($teachers, 'name')),
                'liaisons' => $allRoles ? '' : implode(', ', $liaisons),
                'tables' => $tables,
            ];
        }

        return $classes;
    }

    private static function photoFile(Person $person): string
    {
        $photo = (string) $person->getPhoto()->getPhotoURI();

        return is_file($photo) ? $photo : '';
    }
}
