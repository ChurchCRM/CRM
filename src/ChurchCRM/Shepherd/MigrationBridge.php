<?php

namespace ChurchCRM\Shepherd;

use PDO;
use RuntimeException;

final class MigrationBridge
{
    public static function apply(PDO $db, string $root): void
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tables = (int) $db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn();
        if ($tables === 0) {
            return;
        }
        $hasVersion = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='version_ver'")->fetchColumn();
        if ($hasVersion !== 1) {
            throw new RuntimeException('A partially initialized database requires inspection before migration.');
        }
        $db->exec('CREATE TABLE IF NOT EXISTS shepherd_schema_migrations (migration VARCHAR(128) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $version = $db->query('SELECT ver_version FROM version_ver ORDER BY ver_ID DESC LIMIT 1')->fetchColumn();
        if (!is_string($version)) {
            throw new RuntimeException('A recorded ChurchCRM database version is required.');
        }

        if (version_compare($version, '7.6.4', '<') && $version !== '7.6.2') {
            throw new RuntimeException('The Shepherd bridge requires the confirmed 7.6.2 baseline; found ' . $version);
        }
        if ($version === '7.6.2') {
            self::reconcileAnniversaryQueries($db, $root);
            foreach ([
                'person_per' => ['per_fam_ID', 'per_cls_ID', 'per_fmr_ID'],
                'pledge_plg' => ['plg_FamID', 'plg_FYID', 'plg_fundID', 'plg_depID'],
                'note_nte' => ['nte_per_ID', 'nte_fam_ID'],
            ] as $table => $columns) {
                foreach ($columns as $column) {
                    $check = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND SEQ_IN_INDEX=1');
                    $check->execute([$table, $column]);
                    if ((int) $check->fetchColumn() === 0) {
                        $db->exec("ALTER TABLE `$table` ADD INDEX `idx_$column` (`$column`)");
                    }
                }
            }
            $primary = $db->query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='list_lst' AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX")->fetchAll(PDO::FETCH_COLUMN);
            if ($primary === []) {
                $duplicates = $db->query('SELECT COUNT(*) FROM (SELECT lst_ID,lst_OptionID FROM list_lst GROUP BY lst_ID,lst_OptionID HAVING COUNT(*)>1) duplicates')->fetchColumn();
                if ((int) $duplicates !== 0) {
                    throw new RuntimeException('list_lst contains duplicate keys; reconcile the source data before migration.');
                }
                $db->exec('ALTER TABLE list_lst ADD PRIMARY KEY (lst_ID,lst_OptionID)');
            } elseif ($primary !== ['lst_ID', 'lst_OptionID']) {
                throw new RuntimeException('Unexpected list_lst primary key; refusing to replace it.');
            }
            self::record($db, 'churchcrm-7.6.1-7.6.2-reconciliation');
        }

        $db->exec((string) file_get_contents($root . '/mysql/shepherd/website-content.sql'));
        self::record($db, 'website-content-v1');
    }

    private static function reconcileAnniversaryQueries(PDO $db, string $root): void
    {
        foreach ([300 => 'Wedding Anniversaries', 301 => 'Birthdays & Anniversaries'] as $id => $name) {
            $query = $db->prepare('SELECT qry_Name FROM query_qry WHERE qry_ID=?');
            $query->execute([$id]);
            $existing = $query->fetchColumn();
            if ($existing !== false && $existing !== $name) {
                throw new RuntimeException("Predefined query $id belongs to another report; refusing to overwrite it.");
            }
            $parameter = $db->prepare('SELECT qrp_qry_ID FROM queryparameters_qrp WHERE qrp_ID=?');
            $parameter->execute([$id]);
            $owner = $parameter->fetchColumn();
            if ($owner !== false && (int) $owner !== $id) {
                throw new RuntimeException("Query parameter $id belongs to another report.");
            }
        }
        $db->exec((string) file_get_contents($root . '/mysql/upgrade/7.6.1-add-anniversary-queries.sql'));
    }

    private static function record(PDO $db, string $migration): void
    {
        $statement = $db->prepare('INSERT IGNORE INTO shepherd_schema_migrations (migration) VALUES (?)');
        $statement->execute([$migration]);
    }
}
