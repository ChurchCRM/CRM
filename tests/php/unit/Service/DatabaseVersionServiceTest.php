<?php

declare(strict_types=1);

use ChurchCRM\Service\DatabaseVersionService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

Assert::false(DatabaseVersionService::check('10.0.38-MariaDB')['supported'], 'MariaDB 10.0 cannot index long columns');
Assert::false(DatabaseVersionService::check('5.6.51')['supported'], 'MySQL 5.6 cannot index long columns');
Assert::true(DatabaseVersionService::check('10.2.2-MariaDB')['supported'], 'the MariaDB floor is allowed');
Assert::true(DatabaseVersionService::check('5.7.7')['supported'], 'the MySQL floor is allowed');
Assert::true(DatabaseVersionService::check('8.0.36-0ubuntu0.22.04.1')['supported'], 'a distribution suffix is ignored');
Assert::same('MariaDB', DatabaseVersionService::check('5.5.5-10.11.19-MariaDB-ubu2204')['engine'], 'the 5.5.5- compatibility prefix is stripped');
Assert::same('10.11.19', DatabaseVersionService::check('5.5.5-10.11.19-MariaDB-ubu2204')['version']);
Assert::true(DatabaseVersionService::check('Could not obtain DB Server Version')['supported'], 'an unreadable version does not block');
Assert::null(DatabaseVersionService::getUpgradeBlockMessage('11.4.2-MariaDB'));
Assert::contains('MariaDB 10.0.38', DatabaseVersionService::getUpgradeBlockMessage('10.0.38-MariaDB'));
