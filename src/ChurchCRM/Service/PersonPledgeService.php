<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use PDO;
use Propel\Runtime\Propel;

/**
 * Service for managing individual person attribution for pledges and payments.
 */
class PersonPledgeService
{
    /**
     * Ensure the mapping table exists if needed during legacy runtime checks.
     *
     * @return void
     */
    public static function ensureTableExists(): void
    {
        $sSQL = "CREATE TABLE IF NOT EXISTS `person_pledge_map` (
          `ppm_plg_id` mediumint(9) NOT NULL,
          `ppm_per_id` mediumint(9) NOT NULL,
          PRIMARY KEY (`ppm_plg_id`),
          KEY `idx_person` (`ppm_per_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $connection = Propel::getConnection();
        $connection->exec($sSQL);
    }

    /**
     * Link a pledge to a specific person, or delete the link if person ID is 0.
     *
     * @param int $pledgeId The pledge record ID (plg_plgID).
     * @param int $personId The person record ID (per_ID).
     * @return void
     */
    public static function setPersonForPledge(int $pledgeId, int $personId): void
    {
        if ($pledgeId <= 0) {
            return;
        }

        $connection = Propel::getConnection();

        if ($personId <= 0) {
            $stmt = $connection->prepare('DELETE FROM person_pledge_map WHERE ppm_plg_id = :pledgeId');
            $stmt->execute([':pledgeId' => $pledgeId]);
            return;
        }

        $sSQL = 'INSERT INTO person_pledge_map (ppm_plg_id, ppm_per_id) 
                 VALUES (:pledgeId, :personId) 
                 ON DUPLICATE KEY UPDATE ppm_per_id = :updatePersonId';
        $stmt = $connection->prepare($sSQL);
        $stmt->execute([
            ':pledgeId'        => $pledgeId,
            ':personId'        => $personId,
            ':updatePersonId'  => $personId,
        ]);
    }

    /**
     * Get the individual person ID linked to a pledge.
     *
     * @param int $pledgeId The pledge record ID (plg_plgID).
     * @return int The linked person ID, or 0 if unassigned.
     */
    public static function getPersonForPledge(int $pledgeId): int
    {
        if ($pledgeId <= 0) {
            return 0;
        }

        $map = self::getPersonsForPledges([$pledgeId]);
        return $map[$pledgeId] ?? 0;
    }

    /**
     * Batch lookup linked person IDs for a list of pledge IDs.
     *
     * @param int[] $pledgeIds List of pledge record IDs.
     * @return array<int, int> Map of pledge ID to person ID.
     */
    public static function getPersonsForPledges(array $pledgeIds): array
    {
        $validIds = array_values(array_unique(array_filter(array_map('intval', $pledgeIds), static fn(int $id): bool => $id > 0)));
        if (empty($validIds)) {
            return [];
        }

        $connection = Propel::getConnection();
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $stmt = $connection->prepare("SELECT ppm_plg_id, ppm_per_id FROM person_pledge_map WHERE ppm_plg_id IN ($placeholders)");
        $stmt->execute($validIds);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[(int) $row['ppm_plg_id']] = (int) $row['ppm_per_id'];
        }

        return $results;
    }

    /**
     * Remove mapping record when a pledge is deleted.
     *
     * @param int $pledgeId The pledge record ID.
     * @return void
     */
    public static function deleteMappingForPledge(int $pledgeId): void
    {
        if ($pledgeId <= 0) {
            return;
        }

        $connection = Propel::getConnection();
        $stmt = $connection->prepare('DELETE FROM person_pledge_map WHERE ppm_plg_id = :pledgeId');
        $stmt->execute([':pledgeId' => $pledgeId]);
    }

    /**
     * Get list of family members as key-value pairs (per_ID => FullName) for dropdown.
     *
     * @param int $familyId The family ID.
     * @return array<int, string> Associative array of person IDs to full names.
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

    /**
     * Batch lookup person full names for an array of person IDs.
     *
     * @param int[] $personIds List of person IDs (per_ID).
     * @return array<int, string> Map of person ID to full name.
     */
    public static function getPersonNames(array $personIds): array
    {
        $validIds = array_values(array_unique(array_filter(array_map('intval', $personIds), static fn(int $id): bool => $id > 0)));
        if (empty($validIds)) {
            return [];
        }

        $people = PersonQuery::create()
            ->filterById($validIds)
            ->find();

        $result = [];
        foreach ($people as $person) {
            $result[(int) $person->getId()] = $person->getFirstName() . ' ' . $person->getLastName();
        }

        return $result;
    }
}
