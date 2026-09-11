<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\notifications\BirthdayEmail;
use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;

class BirthdayEmailService
{
    /**
     * Config key holding the date (Y-m-d) birthday emails last went out.
     */
    private const LAST_RUN_CONFIG_NAME = 'sLastBirthdayEmailRunDate';

    /**
     * Sends birthday greeting emails to everyone whose birthday is today,
     * if the feature is enabled and it has not already run today.
     *
     * Safe to call concurrently: the "already ran today" marker is claimed
     * atomically, so exactly one caller per day does the work and the rest
     * return without sending or throwing. Safe to call when the feature is
     * disabled (no-ops immediately).
     *
     * The guard is per-run, not per-recipient: an individual send that fails
     * is logged as a warning and is not retried by a later run today.
     */
    public static function run(): void
    {
        if (!SystemConfig::getBooleanValue('bEnableBirthdayEmails')) {
            return;
        }

        $tz = DateTimeUtils::getConfiguredTimezone();
        $today = new \DateTime('now', $tz);
        $todayString = $today->format('Y-m-d');

        // Claim the day before sending, so a crash cannot result in duplicate
        // emails and so concurrent timer-job requests cannot both send.
        if (!self::claimRunForToday($todayString)) {
            return;
        }

        $logger = LoggerUtils::getAppLogger();
        $sentCount = 0;
        $skippedCount = 0;

        $people = PersonQuery::create()
            ->filterByBirthMonth((int) $today->format('n'))
            ->filterByBirthDay((int) $today->format('j'))
            ->find();

        foreach ($people as $person) {
            $email = $person->getEmail();
            if (empty($email)) {
                $skippedCount++;
                continue;
            }

            try {
                $birthdayEmail = new BirthdayEmail([$email], $person);
                if ($birthdayEmail->send()) {
                    $sentCount++;
                } else {
                    $logger?->warning('BirthdayEmailService: failed to send to person ID ' . $person->getId() . ': ' . $birthdayEmail->getError());
                }
            } catch (\Exception $e) {
                $logger?->warning('BirthdayEmailService: exception sending to person ID ' . $person->getId() . ': ' . $e->getMessage());
            }
        }

        $logger?->info("BirthdayEmailService: sent {$sentCount} birthday email(s), skipped {$skippedCount} (no email on file)");
    }

    /**
     * Atomically claim today's birthday-email run.
     *
     * Returns true for exactly one caller per day. `runTimerJobs` fires on
     * every page load, so several requests routinely reach this point together;
     * a plain read-then-write collided on the `config_cfg` primary key and threw
     * an uncaught "Unable to execute INSERT statement" (issue #9727).
     *
     * Both paths below are single statements, so the database — not PHP —
     * decides the winner:
     *   - row present: a conditional UPDATE that only matches rows not already
     *     holding today's date; losers see zero affected rows.
     *   - row absent: the INSERT itself, whose primary key rejects the losers.
     */
    private static function claimRunForToday(string $todayString): bool
    {
        $existing = ConfigQuery::create()->findOneByName(self::LAST_RUN_CONFIG_NAME);

        if ($existing !== null) {
            if ($existing->getValue() === $todayString) {
                return false;
            }

            $affectedRows = ConfigQuery::create()
                ->filterByName(self::LAST_RUN_CONFIG_NAME)
                ->filterByValue($todayString, Criteria::NOT_EQUAL)
                ->update(['Value' => $todayString]);

            return $affectedRows > 0;
        }

        try {
            $config = new Config();
            $config->setName(self::LAST_RUN_CONFIG_NAME);
            $config->setValue($todayString);
            $config->save();
        } catch (PropelException $e) {
            // Another request inserted the marker between our read and our
            // write. Confirm that is what happened before standing down, so a
            // genuine database failure is still surfaced.
            $winner = ConfigQuery::create()->findOneByName(self::LAST_RUN_CONFIG_NAME);
            if ($winner !== null && $winner->getValue() === $todayString) {
                LoggerUtils::getAppLogger()?->debug('BirthdayEmailService: another run claimed today first');

                return false;
            }

            throw $e;
        }

        return true;
    }
}
