<?php

declare(strict_types=1);

// Existing User lifecycle signatures emit PHP 8.4 deprecations unrelated to this API.
error_reporting(E_ALL & ~E_DEPRECATED);

use ChurchCRM\Authentication\AuthenticationProviders\IAuthenticationProvider;
use ChurchCRM\Authentication\AuthenticationResult;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\LoggerUtils;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;

require __DIR__ . '/../../src/vendor/autoload.php';

if (getenv('PLUGIN_MIGRATION_TEST_PRODUCTION') === '1'
    && (is_dir(__DIR__ . '/../../src/vendor/phpstan')
        || is_dir(__DIR__ . '/../../src/vendor/rector')
        || is_dir(__DIR__ . '/../../src/vendor/perplorm/perpl/src/Propel/Generator/Command'))) {
    throw new RuntimeException('Production fixture must exclude development dependencies and the model generator.');
}

// Fault injection is confined to this test harness, never a production option.
if (defined('MIGRATION_TEST_FAULT_PHASE')) {
    require __DIR__ . '/FaultConnection.php';
}

// All SQL setup/teardown is restricted to a deliberately named disposable DB.
$dsn = getenv('PLUGIN_MIGRATION_TEST_DSN') ?: '';
if (!preg_match('/(?:^|;)dbname=(churchcrm_plugin_migrations_test)(?:;|$)/', $dsn)) {
    throw new RuntimeException('Set PLUGIN_MIGRATION_TEST_DSN to a disposable MySQL/MariaDB database named churchcrm_plugin_migrations_test.');
}
$databaseConfig = [
    'dsn' => $dsn,
    'user' => getenv('PLUGIN_MIGRATION_TEST_USER') ?: 'root',
    'password' => getenv('PLUGIN_MIGRATION_TEST_PASSWORD') ?: '',
    'classname' => defined('MIGRATION_TEST_FAULT_PHASE') ? MigrationFaultConnection::class : 'Propel\Runtime\Connection\ConnectionWrapper',
    'options' => ['ATTR_ERRMODE' => PDO::ERRMODE_EXCEPTION],
];
$manager = new ConnectionManagerSingle('default');
$manager->setConfiguration($databaseConfig);
$container = Propel::getServiceContainer();
$container->setAdapterClass('default', 'mysql');
$container->setConnectionManager($manager);
$container->setDefaultDatasource('default');
require __DIR__ . '/../../src/Include/LoadDatabaseMap.php';
$connection = Propel::getWriteConnection('default');
if ($connection->query('SELECT DATABASE()')->fetchColumn() !== 'churchcrm_plugin_migrations_test') {
    throw new RuntimeException('Refusing to modify a database other than churchcrm_plugin_migrations_test.');
}

// Avoid unrelated filesystem logging/telemetry during integration tests.
(new ReflectionProperty(LoggerUtils::class, 'appLogger'))->setValue(null, new Logger('tests', [new NullHandler()]));

final class MigrationTestAuthentication implements IAuthenticationProvider
{
    public function __construct(public User $user)
    {
    }

    public function getCurrentUser(): ?User
    {
        return $this->user;
    }

    public function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult
    {
        throw new LogicException('Authentication is not performed by this fixture.');
    }

    public function validateUserSessionIsActive(bool $updateLastOperationTimestamp): AuthenticationResult
    {
        throw new LogicException('Session validation is not performed by this fixture.');
    }

    public function endSession(): void
    {
    }

    public function getPasswordChangeURL(): string
    {
        return '';
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$admin = new User();
$admin->setAdmin(true);
$_SESSION['AuthenticationProvider'] = new MigrationTestAuthentication($admin);
$_SESSION['sSoftwareInstalledVersion'] = '7.7.0';
$_SESSION['RemotePluginRegistry'] = [];
