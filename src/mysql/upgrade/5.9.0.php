<?php

use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;

$connection = Propel::getConnection();
$logger = LoggerUtils::getAppLogger();

$logger->info('Starting 5.9.0 upgrade');

// Drop table if exists (IF EXISTS is valid MySQL syntax for DROP TABLE)
$q1 = 'DROP TABLE IF EXISTS canvassdata_can;';
$connection->exec($q1);

// Drop family_fam columns
try {
    $q2 = 'ALTER TABLE family_fam DROP COLUMN `fam_OkToCanvass`;';
    $connection->exec($q2);
} catch (\Exception $e) {
    $logger->warning('Could not remove `family_fam.fam_OkToCanvass`, but this is probably ok');
}

try {
    $q3 = 'ALTER TABLE family_fam DROP COLUMN `fam_Canvasser`;';
    $connection->exec($q3);
} catch (\Exception $e) {
    $logger->warning('Could not remove `family_fam.fam_Canvasser`, but this is probably ok');
}

// Delete from list_lst
try {
    $q4 = 'DELETE FROM list_lst WHERE lst_OptionName = \'bCanvasser\';';
    $connection->exec($q4);
} catch (\Exception $e) {
    $logger->warning('Could not delete from `list_lst`, but this is probably ok');
}

// Delete from query_qry
try {
    $q5 = 'DELETE FROM query_qry WHERE qry_ID = \'27\';';
    $connection->exec($q5);
} catch (\Exception $e) {
    $logger->warning('Could not delete from `query_qry`, but this is probably ok');
}

// Drop user_usr column
try {
    $q6 = 'ALTER TABLE user_usr DROP COLUMN `usr_Canvasser`;';
    $connection->exec($q6);
} catch (\Exception $e) {
    $logger->warning('Could not remove `user_usr.usr_Canvasser`, but this is probably ok');
}

// Delete from permissions
try {
    $q7 = 'DELETE FROM permissions WHERE permission_name = \'canvasser\';';
    $connection->exec($q7);
} catch (\Exception $e) {
    $logger->warning('Could not delete from `permissions`, but this is probably ok');
}

$logger->info('Finished 5.9.0 upgrade');
