<?php

declare(strict_types=1);

use ChurchCRM\Service\DatabaseVersionService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

Assert::false(DatabaseVersionService::check('10.0.38-MariaDB')['supported'], 'MariaDB 10.0 is below the documented minimum');
Assert::false(DatabaseVersionService::check('5.6.51')['supported'], 'MySQL 5.6 is below the documented minimum');
Assert::false(DatabaseVersionService::check('8.0.36')['supported'], 'MySQL 8.0 is below the documented minimum');
Assert::false(DatabaseVersionService::check('10.6.18-MariaDB')['supported'], 'MariaDB 10.6 is below the documented minimum');
Assert::true(DatabaseVersionService::check('10.11.0-MariaDB')['supported'], 'the MariaDB minimum is allowed');
Assert::true(DatabaseVersionService::check('8.4.0')['supported'], 'the MySQL minimum is allowed');
Assert::true(DatabaseVersionService::check('8.4.1-0ubuntu0.24.04.1')['supported'], 'a distribution suffix is ignored');
Assert::same(DatabaseVersionService::MIN_MYSQL, json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/supported-databases.json'), true)['mysql'], 'the minimum matches supported-databases.json');
Assert::same(DatabaseVersionService::MIN_MARIADB, json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/supported-databases.json'), true)['mariadb']);
Assert::same('MariaDB', DatabaseVersionService::check('5.5.5-10.11.19-MariaDB-ubu2204')['engine'], 'the 5.5.5- compatibility prefix is stripped');
Assert::same('10.11.19', DatabaseVersionService::check('5.5.5-10.11.19-MariaDB-ubu2204')['version']);
Assert::true(DatabaseVersionService::check('Could not obtain DB Server Version')['supported'], 'an unreadable version does not block');
Assert::null(DatabaseVersionService::getUpgradeBlockMessage('11.4.2-MariaDB'));
Assert::contains('MariaDB 10.0.38', DatabaseVersionService::getUpgradeBlockMessage('10.0.38-MariaDB'));
Assert::null(DatabaseVersionService::getUpgradeWarningMessage('11.4.2-MariaDB'));
Assert::contains('MariaDB 10.0.38', DatabaseVersionService::getUpgradeWarningMessage('10.0.38-MariaDB'));
Assert::notContains('not started', DatabaseVersionService::getUpgradeWarningMessage('10.0.38-MariaDB'), 'the warning does not claim the upgrade was stopped');
Assert::contains('not started', DatabaseVersionService::getUpgradeBlockMessage('10.0.38-MariaDB'));
