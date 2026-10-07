<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;

/**
 * D31's daily top-up, run from SystemService::runTimerJobs(): every active schedule of an
 * active ministry, Staff this event ones aside, is generated up to the scheduling horizon
 * once a day, so no coordinator has to press Generate to keep a schedule ahead. On the
 * occurrences it creates it assigns each position's saved default volunteer (D32), as a
 * Generate run does; the occurrence unique key makes a repeat run a no-op.
 */
final class VolunteerScheduleTopUp
{
    /** The day (Y-m-d) the top-up last ran: claimed before it runs, like the birthday mail. */
    public const LAST_RUN_DATE_CONFIG = 'sLastVolunteerTopUpRunDate';

    /** JSON `{ranAt, schedules, created, failed, assigned, skipped, unqualified}` of the last run, for Admin → Ministry Settings. */
    public const LAST_RESULT_CONFIG = 'sLastVolunteerTopUpResult';

    /**
     * @param bool $force run even though today's run has happened ("Run background jobs now")
     *
     * @return array{ranAt: string, schedules: int, created: int, failed: int, assigned: int, skipped: int, unqualified: int}|null
     *         null when V2 is off, or today's run has already happened and `$force` is false
     */
    public static function run(bool $force = false): ?array
    {
        if (!User::isVolunteerV2Enabled()) {
            return null;
        }

        if (!VolunteerDailyRun::claim(self::LAST_RUN_DATE_CONFIG, DateTimeUtils::getTodayDate()) && !$force) {
            return null;
        }

        $logger = LoggerUtils::getAppLogger();
        $service = new VolunteerAssignmentService();
        $schedules = VolunteerScheduleQuery::create()
            ->filterByActive(true)
            ->filterByOneOff(false)
            ->useMinistryQuery()
                ->filterByActive(true)
            ->endUse()
            ->orderById()
            ->find();

        $result = [
            'ranAt' => DateTimeUtils::getNowDateTime(),
            'schedules' => 0,
            'created' => 0,
            'failed' => 0,
            'assigned' => 0,
            'skipped' => 0,
            'unqualified' => 0,
        ];
        foreach ($schedules as $schedule) {
            $result['schedules']++;
            try {
                $run = $service->generateWithDefaults($schedule, null, null, null);
                foreach (['created', 'assigned', 'skipped', 'unqualified'] as $key) {
                    $result[$key] += $run[$key];
                }
            } catch (\Throwable $e) {
                // One schedule over the occurrence cap must not stop the others.
                $result['failed']++;
                $logger->warning('Volunteer schedule top-up skipped a schedule', [
                    'scheduleId' => $schedule->getId(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        VolunteerDailyRun::store(self::LAST_RESULT_CONFIG, json_encode($result));
        $logger->info('Volunteer schedules topped up to the scheduling horizon', $result + [
            'horizonWeeks' => VolunteerScheduleService::horizonWeeks(),
            'forced' => $force,
        ]);

        return $result;
    }

    /**
     * @return array{ranAt: string, schedules: int, created: int, failed: int, assigned: int, skipped: int, unqualified: int}|null
     */
    public static function lastResult(): ?array
    {
        $raw = ConfigQuery::create()->findOneByName(self::LAST_RESULT_CONFIG)?->getValue() ?? '';
        $decoded = $raw === '' ? null : json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['ranAt'], $decoded['created'])) {
            return null;
        }

        return [
            'ranAt' => (string) $decoded['ranAt'],
            'schedules' => (int) ($decoded['schedules'] ?? 0),
            'created' => (int) $decoded['created'],
            'failed' => (int) ($decoded['failed'] ?? 0),
            'assigned' => (int) ($decoded['assigned'] ?? 0),
            'skipped' => (int) ($decoded['skipped'] ?? 0),
            'unqualified' => (int) ($decoded['unqualified'] ?? 0),
        ];
    }
}
