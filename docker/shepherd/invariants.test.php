<?php

use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Shepherd\MigrationBridge;

if (getenv('SHEPHERD_DB_PASSWORD') !== 'disposable-test-password') {
    throw new RuntimeException('Refusing to run invariants against a non-test database.');
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/shepherd/livez';
require '/var/www/html/shepherd/Include/LoadConfigs.php';
$checks = 0;
$check = static function (bool $result, string $message) use (&$checks): void {
    if (!$result) {
        throw new RuntimeException($message);
    }
    $checks++;
    echo "PASS $message\n";
};
$member = new LocalAuthentication();
$result = $member->authenticate(new LocalUsernamePasswordRequest('upgrade.member', 'Upgrade-test-password-2026!'));
$check($result->isAuthenticated, 'synthetic member authenticates');
SystemConfig::setValue('iSessionTimeout', '60');
(new ReflectionProperty($member, 'tLastOperationTimestamp'))->setValue($member, time() - 120);
$check(!$member->validateUserSessionIsActive(false)->isAuthenticated, 'idle session expires');
$pending = new LocalAuthentication();
$pending->authenticate(new LocalUsernamePasswordRequest('upgrade.admin', 'Upgrade-test-password-2026!'));
(new ReflectionProperty($pending, 'tLastOperationTimestamp'))->setValue($pending, time() - 1200);
$check(!$pending->validateUserSessionIsActive(false)->isAuthenticated, 'pending second factor expires');
SystemConfig::setValue('iSessionTimeout', '1800');

$db = new PDO('mysql:host=' . getenv('SHEPHERD_DB_HOST'), 'root', 'disposable-root-password', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE shepherd_bridge_invariants');
$db->exec('USE shepherd_bridge_invariants');
$root = '/var/www/html/shepherd';
$rejects = static function (string $message) use ($db, $root, $check): void {
    try {
        MigrationBridge::apply($db, $root);
    } catch (RuntimeException $exception) {
        $check(str_contains($exception->getMessage(), $message), $message);
        return;
    }
    throw new RuntimeException('Expected bridge refusal: ' . $message);
};
try {
    MigrationBridge::apply($db, $root);
    $check((int) $db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() === 0, 'empty database is left for upstream installation');
    $db->exec('CREATE TABLE partial (id INT)');
    $rejects('partially initialized');
    $db->exec('DROP TABLE partial');
    $db->exec(file_get_contents($root . '/mysql/install/Install.sql'));
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `query_qry` (
  `qry_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `qry_SQL` text NOT NULL,
  `qry_Name` varchar(255) NOT NULL default '',
  `qry_Description` text NOT NULL,
  `qry_Count` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`qry_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=101 ;
CREATE TABLE IF NOT EXISTS `queryparameters_qrp` (
  `qrp_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `qrp_qry_ID` mediumint(8) unsigned NOT NULL default '0',
  `qrp_Type` tinyint(3) unsigned NOT NULL default '0',
  `qrp_OptionSQL` text,
  `qrp_Name` varchar(25) default NULL,
  `qrp_Description` text,
  `qrp_Alias` varchar(25) default NULL,
  `qrp_Default` varchar(25) default NULL,
  `qrp_Required` tinyint(3) unsigned NOT NULL default '0',
  `qrp_InputBoxSize` tinyint(3) unsigned NOT NULL default '0',
  `qrp_Validation` varchar(5) NOT NULL default '',
  `qrp_NumericMax` int(11) default NULL,
  `qrp_NumericMin` int(11) default NULL,
  `qrp_AlphaMinLength` int(11) default NULL,
  `qrp_AlphaMaxLength` int(11) default NULL,
  PRIMARY KEY  (`qrp_ID`),
  KEY `qrp_qry_ID` (`qrp_qry_ID`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=102 ;
CREATE TABLE IF NOT EXISTS `note_nte` (
  `nte_ID` mediumint(8) unsigned NOT NULL auto_increment,
  `nte_per_ID` mediumint(8) unsigned NOT NULL default '0',
  `nte_fam_ID` mediumint(8) unsigned NOT NULL default '0',
  `nte_Private` mediumint(8) unsigned NOT NULL default '0',
  `nte_Text` text,
  `nte_DateEntered` datetime NOT NULL,
  `nte_DateLastEdited` datetime default NULL,
  `nte_EnteredBy` mediumint(8) NOT NULL default '0',
  `nte_EditedBy` mediumint(8) unsigned NOT NULL default '0',
  `nte_Type` varchar(50) DEFAULT NULL,
  PRIMARY KEY  (`nte_ID`)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AUTO_INCREMENT=1 ;
SQL); 
    $db->exec("INSERT INTO version_ver (ver_version) VALUES ('7.6.2')");
    MigrationBridge::apply($db, $root);
    $schema = $db->query('SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX')->fetchAll(PDO::FETCH_ASSOC);
    $ledger = $db->query('SELECT * FROM shepherd_schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_ASSOC);
    MigrationBridge::apply($db, $root);
    $check($schema === $db->query('SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX')->fetchAll(PDO::FETCH_ASSOC), 'reconciliation twice preserves schema and indexes');
    $check($ledger === $db->query('SELECT * FROM shepherd_schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_ASSOC), 'reconciliation twice preserves migration ledger');
    $check($db->query('SELECT ver_version FROM version_ver')->fetchColumn() === '7.6.2', 'bridge leaves core version to upstream');
    $db->exec("UPDATE query_qry SET qry_Name='A church-owned report' WHERE qry_ID=300");
    $rejects('belongs to another report');
    $db->exec("UPDATE query_qry SET qry_Name='Wedding Anniversaries' WHERE qry_ID=300");
    $db->exec('ALTER TABLE list_lst DROP PRIMARY KEY');
    $db->exec("INSERT INTO list_lst (lst_ID,lst_OptionID,lst_OptionName) VALUES (999,1,'first'),(999,1,'duplicate')");
    $rejects('duplicate keys');
    $check((int) $db->query('SELECT COUNT(*) FROM list_lst WHERE lst_ID=999')->fetchColumn() === 2, 'ambiguous records are preserved');
} finally {
    $db->exec('DROP DATABASE shepherd_bridge_invariants');
}
echo "$checks invariant checks passed.\n";
