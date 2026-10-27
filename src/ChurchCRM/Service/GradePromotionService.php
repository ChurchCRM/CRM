<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;

/**
 * Moves everyone with a grade up one grade once a year (#10189).
 *
 * `sLastGradePromotionAt` (Y-m-d H:i:s.u, so text order is time order) records the
 * last promotion. The microseconds keep two promotions in one second distinct, so
 * the compare-and-swap below always changes the row it claims. The yearly job runs on or after the promotion date when that
 * marker is still from an earlier year, and a manual promotion counts as this
 * year's. Both claim the marker with one conditional statement first, so page
 * loads racing each other, a re-run or a double click promote only once.
 *
 * Saving the promotion date records the day it was set (`sGradePromotionSetDate`).
 * A date that had already passed that day waits for next year, so choosing June
 * in October does not promote everyone on the next page load.
 */
class GradePromotionService
{
    public const LAST_RUN_CONFIG = 'sLastGradePromotionAt';
    public const LAST_RESULT_CONFIG = 'sLastGradePromotionResult';
    public const TRIGGER_AUTOMATIC = 'automatic';
    public const TRIGGER_MANUAL = 'manual';
    public const SET_DATE_CONFIG = 'sGradePromotionSetDate';
    public const DATE_SETTINGS = ['iGradePromotionMonth', 'iGradePromotionDay'];
    private const MARKER_FORMAT = 'Y-m-d H:i:s.u';

    public static function run(): void
    {
        $today = DateTimeUtils::getToday();
        $dueDate = self::promotionDate((int) $today->format('Y'));
        if ($dueDate === null || $today->format('Y-m-d') < $dueDate || $dueDate < self::setDate()) {
            return;
        }

        $now = $today->format(self::MARKER_FORMAT);
        $previous = self::readMarker();
        if (!self::claimForYear($today->format('Y') . '-01-01 00:00:00', $now)) {
            return;
        }

        try {
            self::promote(self::TRIGGER_AUTOMATIC, Person::SCHEDULED_TASK, $now);
        } catch (\Throwable $e) {
            self::releaseMarker($previous, $now);
            throw $e;
        }
    }

    /**
     * @param string $expectedLastAt the last-promotion marker the admin was shown ('' for never)
     *
     * @return array<string, mixed>|null the run's result, or null when someone promoted in the meantime
     */
    public static function promoteNow(int $userId, string $expectedLastAt): ?array
    {
        $now = DateTimeUtils::getToday()->format(self::MARKER_FORMAT);
        $previous = self::readMarker();
        if (($previous ?? '') !== $expectedLastAt || !self::claimExact($previous, $now)) {
            return null;
        }

        try {
            return self::promote(self::TRIGGER_MANUAL, $userId, $now);
        } catch (\Throwable $e) {
            self::releaseMarker($previous, $now);
            throw $e;
        }
    }

    /**
     * @return array{month: int, day: int, nextDate: ?string, nextDateText: string, lastAt: string, lastRunText: ?string, promotedThisYear: bool, lastResult: ?array, preview: array{moved: int, finished: int}}
     */
    public static function status(): array
    {
        $lastAt = self::readMarker() ?? '';
        $year = (int) DateTimeUtils::getToday()->format('Y');
        $thisYearsDate = self::promotionDate($year);
        $promotedThisYear = $lastAt >= $year . '-01-01 00:00:00';
        $waitsForNextYear = $promotedThisYear || ($thisYearsDate !== null && $thisYearsDate < self::setDate());
        $result = json_decode(self::readConfig(self::LAST_RESULT_CONFIG) ?? '', true);
        $nextDate = self::promotionDate($waitsForNextYear ? $year + 1 : $year);

        return [
            'month' => self::promotionMonth(),
            'day' => (int) SystemConfig::getValue('iGradePromotionDay'),
            'nextDate' => $nextDate,
            'nextDateText' => $nextDate === null ? gettext('Off') : DateTimeUtils::formatDate($nextDate),
            'lastAt' => $lastAt,
            'lastRunText' => $lastAt === '' ? null : DateTimeUtils::formatDate(substr($lastAt, 0, 19), true),
            'promotedThisYear' => $promotedThisYear,
            'lastResult' => is_array($result) ? $result : null,
            'preview' => self::preview(),
        ];
    }

    /**
     * @return array{moved: int, finished: int}
     */
    public static function preview(): array
    {
        $preview = ['moved' => 0, 'finished' => 0];
        $next = GradeService::nextGradeMap();
        foreach (self::gradedPeople() as $gradeId) {
            if (!array_key_exists($gradeId, $next)) {
                continue;
            }
            $preview[$next[$gradeId] === null ? 'finished' : 'moved']++;
        }

        return $preview;
    }

    /**
     * This year's promotion date (Y-m-d), or null when automatic promotion is off.
     * A day past the end of the month falls on its last day.
     */
    public static function promotionDate(int $year): ?string
    {
        $month = self::promotionMonth();
        if ($month === 0) {
            return null;
        }
        $day = max(1, min((int) SystemConfig::getValue('iGradePromotionDay'), DateTimeUtils::getDaysInMonth($month, $year)));

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Called after the promotion month or day is saved.
     */
    public static function recordDateSet(): void
    {
        SystemConfig::setValue(self::SET_DATE_CONFIG, DateTimeUtils::getTodayDate());
    }

    private static function setDate(): string
    {
        return SystemConfig::getValue(self::SET_DATE_CONFIG);
    }

    private static function promotionMonth(): int
    {
        $month = (int) SystemConfig::getValue('iGradePromotionMonth');

        return $month >= 1 && $month <= 12 ? $month : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function promote(string $trigger, int $authorId, string $ranAt): array
    {
        $names = GradeService::names();
        $next = GradeService::nextGradeMap();
        $byGrade = [];
        foreach (self::gradedPeople() as $personId => $gradeId) {
            if (array_key_exists($gradeId, $next)) {
                $byGrade[$gradeId][] = $personId;
            }
        }

        $result = ['ranAt' => $ranAt, 'trigger' => $trigger, 'userId' => $authorId, 'moved' => 0, 'finished' => 0];
        $con = Propel::getWriteConnection(PersonTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            foreach ($byGrade as $gradeId => $personIds) {
                $nextId = $next[$gradeId];
                PersonQuery::create()->filterById($personIds)->update(['GradeId' => $nextId], $con);
                $text = $nextId === null
                    ? sprintf(gettext('Finished %s, so the yearly promotion cleared the grade'), InputUtils::escapeHTML($names[$gradeId]))
                    : sprintf(gettext('Grade changed from %s to %s by the yearly promotion'), InputUtils::escapeHTML($names[$gradeId]), InputUtils::escapeHTML($names[$nextId]));
                foreach ($personIds as $personId) {
                    GradeService::addNote($personId, $text, $authorId, $con);
                }
                $result[$nextId === null ? 'finished' : 'moved'] += count($personIds);
            }
            self::writeConfig(self::LAST_RESULT_CONFIG, json_encode($result), $con);
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }

        LoggerUtils::getAppLogger()->info('Grade promotion', $result);

        return $result;
    }

    /**
     * @return array<int, int> person ID => grade ID, for living people with a grade
     */
    private static function gradedPeople(): array
    {
        $rows = PersonQuery::create()
            ->filterByGradeId(null, Criteria::ISNOTNULL)
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->select(['Id', 'GradeId'])
            ->find();

        $people = [];
        foreach ($rows as $row) {
            $people[(int) $row['Id']] = (int) $row['GradeId'];
        }

        return $people;
    }

    /**
     * Same claim as BirthdayEmailService::claimRunForToday(): an UPDATE that only
     * matches a marker from before this year, or the INSERT whose primary key
     * rejects every request but one. `_or()` keeps the two value filters in one
     * parenthesised group.
     */
    private static function claimForYear(string $yearStart, string $now): bool
    {
        $existing = ConfigQuery::create()->findOneByName(self::LAST_RUN_CONFIG);
        if ($existing !== null) {
            if ((string) $existing->getValue() >= $yearStart) {
                return false;
            }

            return ConfigQuery::create()
                ->filterByName(self::LAST_RUN_CONFIG)
                ->filterByValue($yearStart, Criteria::LESS_THAN)
                ->_or()
                ->filterByValue(null, Criteria::ISNULL)
                ->update(['Value' => $now]) > 0;
        }

        return self::insertMarker($now);
    }

    private static function claimExact(?string $previous, string $now): bool
    {
        if ($previous === null) {
            return self::insertMarker($now);
        }

        return ConfigQuery::create()
            ->filterByName(self::LAST_RUN_CONFIG)
            ->filterByValue($previous)
            ->update(['Value' => $now]) > 0;
    }

    private static function insertMarker(string $now): bool
    {
        try {
            $config = new Config();
            $config->setName(self::LAST_RUN_CONFIG);
            $config->setValue($now);
            $config->save();
        } catch (PropelException $e) {
            if (ConfigQuery::create()->findOneByName(self::LAST_RUN_CONFIG) !== null) {
                LoggerUtils::getAppLogger()->debug('Grade promotion: another request claimed it first (insert failed with: ' . $e->getMessage() . ')');

                return false;
            }

            throw $e;
        }

        return true;
    }

    private static function releaseMarker(?string $previous, string $claimed): void
    {
        $query = ConfigQuery::create()->filterByName(self::LAST_RUN_CONFIG)->filterByValue($claimed);
        if ($previous === null) {
            $query->delete();
        } else {
            $query->update(['Value' => $previous]);
        }
    }

    private static function readMarker(): ?string
    {
        return self::readConfig(self::LAST_RUN_CONFIG);
    }

    private static function readConfig(string $name): ?string
    {
        $config = ConfigQuery::create()->findOneByName($name);

        return $config?->getValue();
    }

    private static function writeConfig(string $name, string $value, $con): void
    {
        $config = ConfigQuery::create()->filterByName($name)->findOne($con) ?? (new Config())->setName($name);
        $config->setValue($value);
        $config->save($con);
    }
}
