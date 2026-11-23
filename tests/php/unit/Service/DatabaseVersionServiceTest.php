<?php

declare(strict_types=1);

use ChurchCRM\Service\DatabaseVersionService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

Assert::false(DatabaseVersionService::check('10.5.29-MariaDB')['supported'], 'MariaDB 10.5 is below the hard minimum');
Assert::false(DatabaseVersionService::check('10.5.29-MariaDB')['installable']);
Assert::false(DatabaseVersionService::check('10.6.18-MariaDB')['supported'], 'MariaDB 10.6 installs below the supported floor');
Assert::true(DatabaseVersionService::check('10.6.18-MariaDB')['installable']);
Assert::true(DatabaseVersionService::check('10.11.0-MariaDB')['supported'], 'MariaDB 10.11 is supported');
Assert::true(DatabaseVersionService::check('10.11.0-MariaDB')['installable']);
Assert::false(DatabaseVersionService::check('5.7.44')['supported'], 'MySQL 5.7 is below the hard minimum');
Assert::false(DatabaseVersionService::check('5.7.44')['installable']);
Assert::false(DatabaseVersionService::check('8.0.36')['supported'], 'MySQL 8.0 installs below the supported floor');
Assert::true(DatabaseVersionService::check('8.0.36')['installable']);
Assert::true(DatabaseVersionService::check('8.4.0')['supported'], 'MySQL 8.4 is supported');
Assert::true(DatabaseVersionService::check('8.4.0')['installable']);
Assert::true(DatabaseVersionService::check('8.4.1-0ubuntu0.24.04.1')['supported'], 'a distribution suffix is ignored');
Assert::true(DatabaseVersionService::check('8.4.1-0ubuntu0.24.04.1')['installable']);
Assert::same(DatabaseVersionService::MIN_MYSQL, json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/supported-databases.json'), true)['mysql'], 'the minimum matches supported-databases.json');
Assert::same(DatabaseVersionService::MIN_MARIADB, json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/supported-databases.json'), true)['mariadb']);
Assert::same('MariaDB', DatabaseVersionService::check('5.5.5-10.11.19-MariaDB-ubu2204')['engine'], 'the 5.5.5- compatibility prefix is stripped');
Assert::same('10.11.19', DatabaseVersionService::check('5.5.5-10.11.19-MariaDB-ubu2204')['version']);
Assert::true(DatabaseVersionService::check('Could not obtain DB Server Version')['supported'], 'an unreadable version does not block');
Assert::true(DatabaseVersionService::check('Could not obtain DB Server Version')['installable']);
Assert::null(DatabaseVersionService::getUpgradeBlockMessage('11.4.2-MariaDB'));
Assert::null(DatabaseVersionService::getUpgradeBlockMessage('10.6.18-MariaDB'), 'a database below the supported floor still upgrades');
Assert::contains('MariaDB 10.5.29', DatabaseVersionService::getUpgradeBlockMessage('10.5.29-MariaDB'));
Assert::contains('not started', DatabaseVersionService::getUpgradeBlockMessage('10.5.29-MariaDB'));
Assert::null(DatabaseVersionService::getBlockMessage('8.0.36'));
Assert::contains('MySQL 5.7.44', DatabaseVersionService::getBlockMessage('5.7.44'));
Assert::null(DatabaseVersionService::getUnsupportedMessage('11.4.2-MariaDB'));
Assert::contains('Your database (MariaDB 10.6.18) is out of support', DatabaseVersionService::getUnsupportedMessage('10.6.18-MariaDB'));
Assert::contains('MariaDB 10.11+ or MySQL 8.4+', DatabaseVersionService::getUnsupportedMessage('8.0.36'));
Assert::same(DatabaseVersionService::getUnsupportedMessage('8.0.36'), DatabaseVersionService::getUpgradeWarningMessage('8.0.36'));
