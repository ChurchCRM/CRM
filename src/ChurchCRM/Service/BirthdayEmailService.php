<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\notifications\BirthdayEmail;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;

class BirthdayEmailService
{
    /**
     * Sends birthday greeting emails to everyone whose birthday is today,
     * if the feature is enabled and it has not already run today.
     *
     * Safe to call multiple times per day (idempotent) and safe to call
     * even when the feature is disabled (no-ops immediately).
     */
    public static function run(): void
    {
        if (!SystemConfig::getBooleanValue('bEnableBirthdayEmails')) {
            return;
        }

        $tz = DateTimeUtils::getConfiguredTimezone();
        $today = new \DateTime('now', $tz);
        $todayString = $today->format('Y-m-d');

        if (SystemConfig::getValue('sLastBirthdayEmailRunDate') === $todayString) {
            // Already ran today; avoid duplicate sends.
            return;
        }

        // Persist before sending so a crash cannot result in duplicate emails.
        SystemConfig::setValue('sLastBirthdayEmailRunDate', $todayString);

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
}
