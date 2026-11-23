<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\FamilyCustomMaster;
use ChurchCRM\model\ChurchCRM\PersonCustomMaster;
use ChurchCRM\Service\CustomFieldDefinitionService;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Tester\Assert;

require dirname(__DIR__, 2) . '/integration/bootstrap.php';

$dsn = getenv('UPGRADE_TEST_DSN');
$user = getenv('UPGRADE_TEST_USER');
$password = getenv('UPGRADE_TEST_PASSWORD');
$admin = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schemas = [];

$newDatabase = static function () use ($dsn, $admin, $user, $password, &$schemas): PDO {
    $name = 'custom_field_test_' . bin2hex(random_bytes(6));
    $admin->exec("CREATE DATABASE `$name`");
    $schemas[] = $name;
    $db = new PDO($dsn . ';dbname=' . $name, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // Minimal copies of the install-schema tables the service touches. The
    // family master table carries the primary key from schema.xml, which the
    // generated Propel mapping expects but Install.sql omits.
    foreach ([
        "CREATE TABLE `person_custom_master` (
          `custom_Order` smallint(6) NOT NULL default '0',
          `custom_Field` varchar(5) NOT NULL default '',
          `custom_Name` varchar(40) NOT NULL default '',
          `custom_Special` mediumint(8) unsigned default NULL,
          `custom_FieldSec` tinyint(4) NOT NULL,
          `type_ID` tinyint(4) NOT NULL default '0',
          PRIMARY KEY (`custom_Field`)
        ) ENGINE=InnoDB",
        "CREATE TABLE `family_custom_master` (
          `fam_custom_Order` smallint(6) NOT NULL default '0',
          `fam_custom_Field` varchar(5) NOT NULL default '',
          `fam_custom_Name` varchar(40) NOT NULL default '',
          `fam_custom_Special` mediumint(8) unsigned default NULL,
          `fam_custom_FieldSec` tinyint(4) NOT NULL default '1',
          `type_ID` tinyint(4) NOT NULL default '0',
          PRIMARY KEY (`fam_custom_Field`)
        ) ENGINE=InnoDB",
        "CREATE TABLE `person_custom` (
          `per_ID` mediumint(9) NOT NULL default '0',
          PRIMARY KEY (`per_ID`)
        ) ENGINE=InnoDB",
        "CREATE TABLE `family_custom` (
          `fam_ID` mediumint(9) NOT NULL default '0',
          PRIMARY KEY (`fam_ID`)
        ) ENGINE=InnoDB",
        "CREATE TABLE `list_lst` (
          `lst_ID` mediumint(8) unsigned NOT NULL default '0',
          `lst_OptionID` mediumint(8) unsigned NOT NULL default '0',
          `lst_OptionSequence` tinyint(3) unsigned NOT NULL default '0',
          `lst_OptionName` varchar(50) NOT NULL default '',
          PRIMARY KEY (`lst_ID`, `lst_OptionID`)
        ) ENGINE=InnoDB",
    ] as $sql) {
        $db->exec($sql);
    }
    $container = Propel::getServiceContainer();
    $container->closeConnections();
    $container->setAdapterClass('default', 'mysql');
    $manager = new ConnectionManagerSingle('default');
    $manager->setConfiguration(['dsn' => $dsn . ';dbname=' . $name, 'user' => $user, 'password' => $password]);
    $container->setConnectionManager($manager);
    $container->setDefaultDatasource('default');
    Propel::disableInstancePooling();
    require APP_ROOT . '/ChurchCRM/Config/PropelDatabaseMap.php';

    return $db;
};

/** Adds a person field definition plus its value column. */
$addPersonField = static function (PDO $db, string $field, int $order, int $typeId, string $name, ?int $special = null): void {
    (new PersonCustomMaster())
        ->setId($field)
        ->setOrder($order)
        ->setName($name)
        ->setSpecial($special)
        ->setFieldSecurity(1)
        ->setTypeId($typeId)
        ->save();
    $db->exec('ALTER TABLE `person_custom` ADD COLUMN `' . $field . '` varchar(50) DEFAULT NULL');
};

/** Adds a family field definition plus its value column. */
$addFamilyField = static function (PDO $db, string $field, int $order, int $typeId, string $name, ?int $special = null): void {
    (new FamilyCustomMaster())
        ->setField($field)
        ->setOrder($order)
        ->setName($name)
        ->setCustomSpecial($special)
        ->setFieldSecurity(1)
        ->setTypeId($typeId)
        ->save();
    $db->exec('ALTER TABLE `family_custom` ADD COLUMN `' . $field . '` varchar(50) DEFAULT NULL');
};

$addListOptions = static function (PDO $db, int $listId, array $options): void {
    $stmt = $db->prepare('INSERT INTO `list_lst` (`lst_ID`, `lst_OptionID`, `lst_OptionSequence`, `lst_OptionName`) VALUES (?, ?, ?, ?)');
    foreach ($options as $sequence => $optionName) {
        $stmt->execute([$listId, $sequence + 1, $sequence + 1, $optionName]);
    }
};

$personOrder = static function (PDO $db, string $field): int {
    $stmt = $db->prepare('SELECT `custom_Order` FROM `person_custom_master` WHERE `custom_Field` = ?');
    $stmt->execute([$field]);

    return (int) $stmt->fetchColumn();
};

$familyOrder = static function (PDO $db, string $field): int {
    $stmt = $db->prepare('SELECT `fam_custom_Order` FROM `family_custom_master` WHERE `fam_custom_Field` = ?');
    $stmt->execute([$field]);

    return (int) $stmt->fetchColumn();
};

$definitionCount = static function (PDO $db, string $recordType, string $field): int {
    [$table, $column] = $recordType === 'person'
        ? ['person_custom_master', 'custom_Field']
        : ['family_custom_master', 'fam_custom_Field'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
    $stmt->execute([$field]);

    return (int) $stmt->fetchColumn();
};

$listOptionCount = static function (PDO $db, int $listId): int {
    $stmt = $db->prepare('SELECT COUNT(*) FROM `list_lst` WHERE `lst_ID` = ?');
    $stmt->execute([$listId]);

    return (int) $stmt->fetchColumn();
};

$columnExists = static function (PDO $db, string $table, string $column): bool {
    $stmt = $db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $db->quote($column));

    return $stmt->fetch() !== false;
};

try {
    $service = new CustomFieldDefinitionService();
    $db = $newDatabase();

    $addPersonField($db, 'c1', 1, 3, 'Person Text');
    $addPersonField($db, 'c2', 2, 12, 'Person Dropdown', 77);
    $addPersonField($db, 'c3', 3, 1, 'Person Flag');
    $addListOptions($db, 77, ['Red', 'Blue']);
    $addListOptions($db, 78, ['Unrelated']);
    $db->exec("INSERT INTO `person_custom` (`per_ID`, `c1`, `c2`, `c3`) VALUES (1, 'alpha', '2', '1')");

    $addFamilyField($db, 'c1', 1, 3, 'Family Text');
    $addFamilyField($db, 'c2', 2, 12, 'Family Dropdown', 88);
    $addFamilyField($db, 'c3', 3, 1, 'Family Flag');
    $addListOptions($db, 88, ['Small', 'Large']);
    $db->exec("INSERT INTO `family_custom` (`fam_ID`, `c1`, `c2`, `c3`) VALUES (1, 'one', '2', '1')");

    $personField = $service->find('person', 'c2');
    Assert::true($personField instanceof PersonCustomMaster, 'person find returns a person definition');
    Assert::same('Person Dropdown', $personField->getName());
    Assert::same(2, (int) $personField->getOrder());
    Assert::same(12, (int) $personField->getTypeId());
    Assert::same(77, (int) $personField->getSpecial());

    $familyField = $service->find('family', 'c2');
    Assert::true($familyField instanceof FamilyCustomMaster, 'family find returns a family definition');
    Assert::same('Family Dropdown', $familyField->getName());
    Assert::same(2, (int) $familyField->getOrder());
    Assert::same(88, (int) $familyField->getCustomSpecial());

    Assert::null($service->find('person', 'c9999'), 'unknown person field is not found');
    Assert::null($service->find('family', 'c9999'), 'unknown family field is not found');
    Assert::null($service->find('person', 'per_ID'), 'a non-custom column is not a custom field');
    Assert::exception(
        static fn () => $service->find('group', 'c1'),
        InvalidArgumentException::class,
        'Unknown custom field record type: group',
    );

    Assert::true($service->move($service->find('person', 'c2'), 'up'), 'c2 moves up');
    Assert::same(1, (int) $service->find('person', 'c2')->getOrder(), 'moved field reads back at order 1');
    Assert::same(1, $personOrder($db, 'c2'), 'reordering persisted to the database');
    Assert::same(2, $personOrder($db, 'c1'), 'the neighbour takes the vacated order');
    Assert::false($service->move($service->find('person', 'c2'), 'up'), 'no field sits above the top one');
    Assert::same(1, $personOrder($db, 'c2'), 'a refused move at the top changes nothing');
    Assert::true($service->move($service->find('person', 'c2'), 'down'), 'c2 moves back down');
    Assert::same([1, 2, 3], [$personOrder($db, 'c1'), $personOrder($db, 'c2'), $personOrder($db, 'c3')], 'orders are restored');
    Assert::false($service->move($service->find('person', 'c3'), 'down'), 'no field sits below the bottom one');
    Assert::same(3, $personOrder($db, 'c3'), 'a refused move at the bottom changes nothing');
    Assert::true($service->move($service->find('person', 'c1'), 'down'), 'c1 moves down');
    Assert::same(2, $personOrder($db, 'c1'), 'c1 takes order 2 after moving down');
    Assert::true($service->move($service->find('person', 'c1'), 'up'), 'c1 moves back up');
    Assert::same([1, 2, 3], [$personOrder($db, 'c1'), $personOrder($db, 'c2'), $personOrder($db, 'c3')], 'orders are restored again');

    foreach (['sideways', ''] as $direction) {
        Assert::exception(
            static fn () => $service->move($service->find('person', 'c1'), $direction),
            InvalidArgumentException::class,
            'Direction must be "up" or "down"',
        );
    }
    Assert::same([1, 2, 3], [$personOrder($db, 'c1'), $personOrder($db, 'c2'), $personOrder($db, 'c3')], 'a rejected direction changes nothing');

    Assert::true($service->move($service->find('family', 'c2'), 'up'), 'family c2 moves up');
    Assert::same(1, $familyOrder($db, 'c2'), 'family reordering persisted to the database');
    Assert::same(2, $familyOrder($db, 'c1'), 'family neighbour takes the vacated order');
    Assert::false($service->move($service->find('family', 'c2'), 'up'), 'family top boundary refuses');
    Assert::true($service->move($service->find('family', 'c2'), 'down'), 'family c2 moves back down');
    Assert::false($service->move($service->find('family', 'c3'), 'down'), 'family bottom boundary refuses');
    Assert::same([1, 2, 3], [$familyOrder($db, 'c1'), $familyOrder($db, 'c2'), $familyOrder($db, 'c3')], 'family orders are restored');

    $service->delete($service->find('person', 'c2'));
    Assert::null($service->find('person', 'c2'), 'deleted person field is gone');
    Assert::same(0, $definitionCount($db, 'person', 'c2'), 'deleted person definition row is gone');
    Assert::false($columnExists($db, 'person_custom', 'c2'), 'deleted person value column is gone');
    Assert::true($columnExists($db, 'person_custom', 'c1'), 'other person columns survive');
    Assert::true($columnExists($db, 'person_custom', 'c3'), 'other person columns survive');
    Assert::same(0, $listOptionCount($db, 77), 'the deleted drop-down list options are gone');
    Assert::same(1, $listOptionCount($db, 78), 'an unrelated list is untouched');
    Assert::same(2, $personOrder($db, 'c3'), 'later fields move up one place after a delete');
    Assert::same(1, $personOrder($db, 'c1'), 'earlier fields keep their order after a delete');
    Assert::same(
        ['alpha', '1'],
        $db->query('SELECT `c1`, `c3` FROM `person_custom` WHERE `per_ID` = 1')->fetch(PDO::FETCH_NUM),
        'stored values in the surviving columns are untouched',
    );

    $service->delete($service->find('family', 'c1'));
    Assert::null($service->find('family', 'c1'), 'deleted family field is gone');
    Assert::false($columnExists($db, 'family_custom', 'c1'), 'deleted family value column is gone');
    Assert::same(1, $familyOrder($db, 'c2'), 'family fields after a delete move up one place');
    Assert::same(2, $familyOrder($db, 'c3'), 'family fields after a delete move up one place');

    $service->delete($service->find('family', 'c2'));
    Assert::null($service->find('family', 'c2'), 'deleted family drop-down is gone');
    Assert::false($columnExists($db, 'family_custom', 'c2'), 'deleted family drop-down column is gone');
    Assert::same(0, $listOptionCount($db, 88), 'the deleted family drop-down list options are gone');
    Assert::same(1, $familyOrder($db, 'c3'), 'the last family field moves up to order 1');
    Assert::same(
        ['1'],
        $db->query('SELECT `c3` FROM `family_custom` WHERE `fam_ID` = 1')->fetch(PDO::FETCH_NUM),
        'stored family values in the surviving column are untouched',
    );

    Assert::exception(
        static fn () => $service->delete((new PersonCustomMaster())->setId('xx')),
        InvalidArgumentException::class,
        'Invalid custom field column: xx',
    );

    // Regression test for #11602: a definition object fetched before another
    // move must not drive the swap from its stale order. The order is changed
    // with SQL, as a concurrent move would, so the object in hand still says
    // 1 while the database says 2. The service re-reads the row under a write
    // lock inside the transaction, so the move below uses the current order
    // (2), not the stale one (1). On the old code the stale lookup swaps with
    // the wrong neighbour and the definition lands at order 2, not 3.
    $staleDb = $newDatabase();
    $addPersonField($staleDb, 'c1', 1, 3, 'Person Text');
    $addPersonField($staleDb, 'c2', 2, 3, 'Person Middle');
    $addPersonField($staleDb, 'c3', 3, 3, 'Person Last');
    $addFamilyField($staleDb, 'c1', 1, 3, 'Family Text');
    $addFamilyField($staleDb, 'c2', 2, 3, 'Family Middle');
    $addFamilyField($staleDb, 'c3', 3, 3, 'Family Last');

    $stalePerson = $service->find('person', 'c1');
    Assert::same(1, (int) $stalePerson->getOrder(), 'stale person definition starts at order 1');
    // A concurrent move swaps c1 and c2 in the database only, so the pooled
    // object still says order 1 while the row says 2.
    $staleDb->exec("UPDATE `person_custom_master` SET `custom_Order` = 2 WHERE `custom_Field` = 'c1'");
    $staleDb->exec("UPDATE `person_custom_master` SET `custom_Order` = 1 WHERE `custom_Field` = 'c2'");
    Assert::same(1, (int) $stalePerson->getOrder(), 'the pooled object still says order 1');
    Assert::true($service->move($stalePerson, 'down'), 'a stale person definition still moves down');
    Assert::same(3, $personOrder($staleDb, 'c1'), 'stale person definition lands at order 3, not 2');
    Assert::same([1, 2, 3], [$personOrder($staleDb, 'c2'), $personOrder($staleDb, 'c3'), $personOrder($staleDb, 'c1')], 'person orders stay a unique contiguous sequence');

    $staleFamily = $service->find('family', 'c1');
    Assert::same(1, (int) $staleFamily->getOrder(), 'stale family definition starts at order 1');
    $staleDb->exec("UPDATE `family_custom_master` SET `fam_custom_Order` = 2 WHERE `fam_custom_Field` = 'c1'");
    $staleDb->exec("UPDATE `family_custom_master` SET `fam_custom_Order` = 1 WHERE `fam_custom_Field` = 'c2'");
    Assert::same(1, (int) $staleFamily->getOrder(), 'the pooled object still says order 1');
    Assert::true($service->move($staleFamily, 'down'), 'a stale family definition still moves down');
    Assert::same(3, $familyOrder($staleDb, 'c1'), 'stale family definition lands at order 3, not 2');
    Assert::same([1, 2, 3], [$familyOrder($staleDb, 'c2'), $familyOrder($staleDb, 'c3'), $familyOrder($staleDb, 'c1')], 'family orders stay a unique contiguous sequence');

    // The value table is gone, so the ALTER TABLE in delete() fails after the
    // drop-down options were already removed. The definition must survive.
    $failedDb = $newDatabase();
    $addPersonField($failedDb, 'c1', 1, 12, 'Orphaned Dropdown', 77);
    $addListOptions($failedDb, 77, ['Red', 'Blue']);
    $failedDb->exec("INSERT INTO `person_custom` (`per_ID`, `c1`) VALUES (1, '2')");
    $doomedDefinition = $service->find('person', 'c1');
    $failedDb->exec('DROP TABLE `person_custom`');
    Assert::exception(
        static fn () => $service->delete($doomedDefinition),
        Throwable::class,
    );
    Assert::notNull($service->find('person', 'c1'), 'a failed column drop leaves the definition in place');
    Assert::same(1, $definitionCount($failedDb, 'person', 'c1'), 'the definition row survives a failed column drop');
    Assert::same(0, $listOptionCount($failedDb, 77), 'drop-down options were already removed before the column drop failed');
} finally {
    Propel::getServiceContainer()->closeConnections();
    foreach ($schemas as $schema) {
        $admin->exec("DROP DATABASE `$schema`");
    }
}
