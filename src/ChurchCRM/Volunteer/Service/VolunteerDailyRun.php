<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;

/**
 * The once-a-day claim of the Volunteer v2 daily timer jobs — the schedule top-up (D31) and
 * the gap alert (#10372) — kept in `config_cfg` under each job's own name.
 */
final class VolunteerDailyRun
{
    /**
     * True for exactly one caller per day, however many page loads reach the timer jobs
     * together: both paths are single statements, so the database picks the winner — the
     * claim BirthdayEmailService::claimRunForToday() makes (#9727), including its NULL case.
     */
    public static function claim(string $configName, string $today): bool
    {
        $existing = ConfigQuery::create()->findOneByName($configName);
        if ($existing !== null) {
            if ($existing->getValue() === $today) {
                return false;
            }

            return ConfigQuery::create()
                ->filterByName($configName)
                ->filterByValue($today, Criteria::NOT_EQUAL)
                ->_or()
                ->filterByValue(null, Criteria::ISNULL)
                ->update(['Value' => $today]) > 0;
        }

        try {
            self::store($configName, $today);
        } catch (PropelException $e) {
            if (ConfigQuery::create()->findOneByName($configName)?->getValue() === $today) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * Written straight to `config_cfg` rather than through SystemConfig, whose per-request
     * cache would not know about a row another request inserted.
     */
    public static function store(string $name, string $value): void
    {
        $config = ConfigQuery::create()->findOneByName($name) ?? (new Config())->setName($name);
        $config->setValue($value);
        $config->save();
    }
}
