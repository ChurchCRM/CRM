<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PersonQuery;

class PersonPledgeService
{
    /**
     * Ensure the mapping table exists
     */
    public static function ensureTableExists(): void
    {
        $sSQL = "CREATE TABLE IF NOT EXISTS `person_pledge_map` (
          `ppm_plg_id` mediumint(9) NOT NULL,
          `ppm_per_id` mediumint(9) NOT NULL,
          PRIMARY KEY (`ppm_plg_id`),
          KEY `idx_person` (`ppm_per_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        RunQuery($sSQL);
    }

    /**
     * Link a pledge to a specific person (or delete link if person ID is 0)
     */
    public static function setPersonForPledge(int $pledgeId, int $personId): void
    {
        self::ensureTableExists();

        if ($pledgeId <= 0) {
            return;
        }

        if ($personId <= 0) {
            $sSQL = "DELETE FROM person_pledge_map WHERE ppm_plg_id = " . (int) $pledgeId;
            RunQuery($sSQL);
            return;
        }

        $sSQL = "INSERT INTO person_pledge_map (ppm_plg_id, ppm_per_id) 
                VALUES (" . (int) $pledgeId . ", " . (int) $personId . ") 
                ON DUPLICATE KEY UPDATE ppm_per_id = " . (int) $personId;
        RunQuery($sSQL);
    }

    /**
     * Get the individual person ID linked to a pledge
     */
    public static function getPersonForPledge(int $pledgeId): int
    {
        self::ensureTableExists();

        if ($pledgeId <= 0) {
            return 0;
        }

        $sSQL = "SELECT ppm_per_id FROM person_pledge_map WHERE ppm_plg_id = " . (int) $pledgeId;
        $rs = RunQuery($sSQL);
        if ($rs && $row = mysqli_fetch_assoc($rs)) {
            return (int) $row['ppm_per_id'];
        }

        return 0;
    }

    /**
     * Remove mapping record when a pledge is deleted
     */
    public static function deleteMappingForPledge(int $pledgeId): void
    {
        self::ensureTableExists();

        if ($pledgeId <= 0) {
            return;
        }

        $sSQL = "DELETE FROM person_pledge_map WHERE ppm_plg_id = " . (int) $pledgeId;
        RunQuery($sSQL);
    }

    /**
     * Get list of family members as key-value pairs (per_ID => FullName) for dropdown
     */
    public static function getFamilyMembers(int $familyId): array
    {
        if ($familyId <= 0) {
            return [];
        }

        $members = PersonQuery::create()
            ->filterByFamId($familyId)
            ->orderByFirstName()
            ->find();

        $result = [];
        foreach ($members as $person) {
            $result[$person->getId()] = $person->getFirstName() . ' ' . $person->getLastName();
        }

        return $result;
    }
}
