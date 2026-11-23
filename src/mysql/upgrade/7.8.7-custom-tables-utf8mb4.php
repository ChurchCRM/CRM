<?php

use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;

// Custom person, family and group-property fields store typed text in columns added at run
// time, so these tables need utf8mb4 for 4-byte characters (emoji). Group-property table
// names depend on the install, so they are found here instead of listed in SQL.

$connection = Propel::getConnection();
$logger = LoggerUtils::getAppLogger();

$tables = $connection->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_TYPE = 'BASE TABLE'
       AND (TABLE_NAME IN ('person_custom', 'family_custom')
            OR (TABLE_NAME REGEXP '^groupprop_[0-9]+$'))
       AND TABLE_COLLATION <> 'utf8mb4_unicode_ci'"
)->fetchAll(\PDO::FETCH_COLUMN);

if ($tables === []) {
    return;
}

$inList = implode(',', array_map([$connection, 'quote'], $tables));

// Check every table before the first ALTER, so a failing site is not left half converted.
$maxKeyBytes = (int) $connection->query(
    "SELECT IFNULL(MAX(k.key_bytes), 0) FROM (
        SELECT SUM(CASE WHEN c.CHARACTER_MAXIMUM_LENGTH IS NULL THEN 8
                        ELSE IFNULL(s.SUB_PART, c.CHARACTER_MAXIMUM_LENGTH) * 4 END) AS key_bytes
        FROM information_schema.STATISTICS s
        JOIN information_schema.COLUMNS c
          ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME
        WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME IN ($inList)
        GROUP BY s.TABLE_NAME, s.INDEX_NAME
    ) k"
)->fetchColumn();
if ($maxKeyBytes > 3072) {
    throw new \RuntimeException('Custom-field tables not converted to utf8mb4: an index would exceed 3072 bytes.');
}

$charForeignKeys = (int) $connection->query(
    "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE u
     JOIN information_schema.COLUMNS c
       ON c.TABLE_SCHEMA = u.TABLE_SCHEMA AND c.TABLE_NAME = u.TABLE_NAME AND c.COLUMN_NAME = u.COLUMN_NAME
     WHERE u.TABLE_SCHEMA = DATABASE() AND u.REFERENCED_TABLE_NAME IS NOT NULL
       AND c.CHARACTER_SET_NAME IS NOT NULL
       AND (u.TABLE_NAME IN ($inList) OR u.REFERENCED_TABLE_NAME IN ($inList))"
)->fetchColumn();
if ($charForeignKeys > 0) {
    throw new \RuntimeException('Custom-field tables not converted to utf8mb4: a foreign key uses a character column.');
}

foreach ($tables as $table) {
    $connection->exec('ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $logger->info('Converted ' . $table . ' to utf8mb4');
}
