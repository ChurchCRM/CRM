<?php

declare(strict_types=1);

/** Populate the 7.8.0 checkpoint database with deterministic synthetic records. */

$host = getenv('UPGRADE_BENCHMARK_HOST') ?: '127.0.0.1';
$port = (int) (getenv('UPGRADE_BENCHMARK_PORT') ?: '3306');
$user = getenv('UPGRADE_BENCHMARK_USER') ?: 'root';
$password = getenv('UPGRADE_BENCHMARK_PASSWORD') ?: '';
$database = getenv('UPGRADE_BENCHMARK_DATABASE') ?: 'churchcrm_benchmark';
$db = new mysqli($host, $user, $password, $database, $port);
$db->set_charset('utf8mb4');
$db->query("SET SESSION sql_mode = 'NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

$counts = [];
foreach (['family_fam', 'person_per', 'pledge_plg', 'email_log_eml', 'event_attend', 'note_nte'] as $table) {
    $counts[$table] = (int) $db->query("SELECT COUNT(*) AS n FROM `$table`")->fetch_assoc()['n'];
}
$alreadyGenerated = (int) $db->query("SELECT COUNT(*) AS n FROM email_log_eml WHERE eml_Address LIKE 'benchmark%@example.test'")->fetch_assoc()['n'];
$alreadyGenerated += (int) $db->query("SELECT COUNT(*) AS n FROM pledge_plg WHERE plg_GroupKey LIKE 'benchmark-%'")->fetch_assoc()['n'];
$alreadyGenerated += (int) $db->query("SELECT COUNT(*) AS n FROM note_nte WHERE nte_Type = 'benchmark'")->fetch_assoc()['n'];
$alreadyGenerated += (int) $db->query("SELECT COUNT(*) AS n FROM event_attend ea JOIN events_event e USING (event_id) WHERE e.event_title LIKE 'Benchmark Service %'")->fetch_assoc()['n'];
if ($alreadyGenerated > 0) {
    throw new RuntimeException('Benchmark rows are already present; start again from an empty checkpoint database.');
}

$familyTarget = 9000;
$personTarget = 25000;
$familyStart = (int) $db->query('SELECT COALESCE(MAX(fam_ID), 0) + 1 AS n FROM family_fam')->fetch_assoc()['n'];
$familiesToAdd = max(0, $familyTarget - $counts['family_fam']);
insertBatches($db, $familiesToAdd, 1000, static function (int $start, int $end) use ($familyStart): string {
    $rows = [];
    for ($i = $start; $i <= $end; $i++) {
        $id = $familyStart + $i - 1;
        $rows[] = "('Benchmark Family $id', '1 Benchmark Road', 'Benchmark City', 'QC', 'H0H0H0', 'Canada', NOW(), 1)";
    }
    return 'INSERT INTO family_fam (fam_Name, fam_Address1, fam_City, fam_State, fam_Zip, fam_Country, fam_DateEntered, fam_EnteredBy) VALUES ' . implode(',', $rows);
});

$personStart = (int) $db->query('SELECT COALESCE(MAX(per_ID), 0) + 1 AS n FROM person_per')->fetch_assoc()['n'];
$familiesFirst = $familyStart;
$peopleToAdd = max(0, $personTarget - $counts['person_per']);
insertBatches($db, $peopleToAdd, 1000, static function (int $start, int $end) use ($personStart, $familiesFirst, $familiesToAdd): string {
    $rows = [];
    for ($i = $start; $i <= $end; $i++) {
        $personId = $personStart + $i - 1;
        $familyId = $familiesFirst + (($i - 1) % max(1, $familiesToAdd));
        $rows[] = "('Benchmark$personId', 'Member$personId', $familyId, 'benchmark$personId@example.test', NOW(), 1)";
    }
    return 'INSERT INTO person_per (per_FirstName, per_LastName, per_fam_ID, per_Email, per_DateEntered, per_EnteredBy) VALUES ' . implode(',', $rows);
});

$personFirst = $personStart;
$eventFirst = (int) $db->query('SELECT COALESCE(MAX(event_id), 0) + 1 AS n FROM events_event')->fetch_assoc()['n'];
$events = [];
for ($i = 0; $i < 4; $i++) {
    $title = $db->real_escape_string('Benchmark Service ' . ($i + 1));
    $db->query("INSERT INTO events_event (event_title, event_start, event_end) VALUES ('$title', '2025-01-05 10:00:00', '2025-01-05 11:30:00')");
    $events[] = $eventFirst + $i;
}

insertBatches($db, $personTarget * 4, 1000, static function (int $start, int $end) use ($personFirst, $events, $personTarget): string {
    $rows = [];
    for ($i = $start; $i <= $end; $i++) {
        $personId = $personFirst + (int) floor(($i - 1) / 4);
        $eventId = $events[($i - 1) % 4];
        $rows[] = "($eventId, $personId, '2025-01-05 10:00:00')";
    }
    return 'INSERT INTO event_attend (event_id, person_id, checkin_date) VALUES ' . implode(',', $rows);
});

insertBatches($db, 50000, 1000, static function (int $start, int $end) use ($personFirst, $familyStart, $personTarget, $familyTarget): string {
    $rows = [];
    for ($i = $start; $i <= $end; $i++) {
        $personId = $personFirst + (($i - 1) % $personTarget);
        $familyId = $familyStart + (($i - 1) % $familyTarget);
        $rows[] = "($personId, $familyId, 0, 'Benchmark note $i', NOW(), 1, 1, 'benchmark')";
    }
    return 'INSERT INTO note_nte (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_DateEntered, nte_EnteredBy, nte_EditedBy, nte_Type) VALUES ' . implode(',', $rows);
});

$pledgePairs = 200000;
insertBatches($db, $pledgePairs, 500, static function (int $start, int $end) use ($familyStart, $familyTarget): string {
    $rows = [];
    for ($i = $start; $i <= $end; $i++) {
        $familyId = $familyStart + (($i - 1) % $familyTarget);
        $date = sprintf('%04d-%02d-15', 2016 + (($i - 1) % 10), 1 + (($i - 1) % 12));
        $key = 'benchmark-' . str_pad((string) $i, 8, '0', STR_PAD_LEFT);
        $rows[] = "($familyId, '$date', 100.00, 'Monthly', 'CHECK', '$date', 0, 'Pledge', 1, 0.00, '$key')";
    }
    return 'INSERT INTO pledge_plg (plg_FamID, plg_date, plg_amount, plg_schedule, plg_method, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_NonDeductible, plg_GroupKey) VALUES ' . implode(',', $rows);
});
insertBatches($db, $pledgePairs, 500, static function (int $start, int $end) use ($familyStart, $familyTarget): string {
    $rows = [];
    for ($i = $start; $i <= $end; $i++) {
        $familyId = $familyStart + (($i - 1) % $familyTarget);
        $date = sprintf('%04d-%02d-15', 2016 + (($i - 1) % 10), 1 + (($i - 1) % 12));
        $key = 'benchmark-' . str_pad((string) $i, 8, '0', STR_PAD_LEFT);
        $rows[] = "($familyId, '$date', 100.00, 'Monthly', 'CHECK', '$date', 0, 'Payment', 1, 0.00, '$key')";
    }
    return 'INSERT INTO pledge_plg (plg_FamID, plg_date, plg_amount, plg_schedule, plg_method, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_NonDeductible, plg_GroupKey) VALUES ' . implode(',', $rows);
});
insertBatches($db, $pledgePairs, 1000, static function (int $start, int $end): string {
    $rows = [];
    for ($i = $start; $i <= $end; $i++) {
        $key = 'benchmark-' . str_pad((string) $i, 8, '0', STR_PAD_LEFT);
        $rows[] = "('$key', NULL, 1, 1)";
    }
    return 'INSERT INTO pledge_denominations_pdem (pdem_plg_GroupKey, plg_depID, pdem_denominationID, pdem_denominationQuantity) VALUES ' . implode(',', $rows);
});

insertBatches($db, 500000, 1000, static function (int $start, int $end) use ($personFirst, $familyStart, $personTarget, $familyTarget): string {
    $rows = [];
    for ($i = $start; $i <= $end; $i++) {
        $personId = $personFirst + (($i - 1) % $personTarget);
        $familyId = $familyStart + (($i - 1) % $familyTarget);
        $rows[] = "($personId, $familyId, 'person$personId@example.test', 'composer', 'Benchmark message', 'sent', 'benchmark-$i@example.test', NOW())";
    }
    return 'INSERT INTO email_log_eml (eml_per_ID, eml_fam_ID, eml_Address, eml_Kind, eml_Subject, eml_Status, eml_MessageID, eml_DateSent) VALUES ' . implode(',', $rows);
});

foreach (['family_fam', 'person_per', 'pledge_plg', 'email_log_eml', 'event_attend', 'note_nte'] as $table) {
    $count = (int) $db->query("SELECT COUNT(*) AS n FROM `$table`")->fetch_assoc()['n'];
    printf("%s\t%d\n", $table, $count);
}

function insertBatches(mysqli $db, int $count, int $batchSize, callable $queryBuilder): void
{
    for ($start = 1; $start <= $count; $start += $batchSize) {
        $end = min($count, $start + $batchSize - 1);
        $db->query($queryBuilder($start, $end));
    }
}
