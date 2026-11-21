<?php

use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\DatabaseVersionService;
use ChurchCRM\Service\LocaleService;
use ChurchCRM\Service\TelemetryService;
use ChurchCRM\Utils\VersionUtils;
use ChurchCRM\Utils\URLValidator;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/', function (RouteCollectorProxy $group): void {
    $getHandler = function (Request $request, Response $response, array $args): Response {
        $renderer = new PhpRenderer('templates/');
        $renderPage = 'setup-steps.php';
        
        try {
            if (version_compare(phpversion(), VersionUtils::getRequiredPhpVersion(), '<')) {
                $renderPage = 'setup-error.php';
            }
        } catch (\RuntimeException $e) {
            // System cannot determine PHP requirements during setup - show error
            $renderPage = 'setup-error.php';
        }

        // Use GLOBALS instead of SystemURLs (Config.php doesn't exist during setup)
        return $renderer->render($response, $renderPage, ['sRootPath' => $GLOBALS['CHURCHCRM_SETUP_ROOT_PATH'] ?? '']);
    };

    $group->get('', $getHandler);
    $group->get('/', $getHandler);

    $group->get('SystemIntegrityCheck', function (Request $request, Response $response, array $args): Response {
        $integrityStatus = AppIntegrityService::verifyApplicationIntegrity();

        return SlimUtils::renderJSON($response, $integrityStatus);
    });

    $group->get('SystemPrerequisiteCheck', function (Request $request, Response $response, array $args): Response {
        $required = AppIntegrityService::getApplicationPrerequisites();
        AppIntegrityService::reportUnmet($required);

        return SlimUtils::renderJSON($response, $required);
    });

    $group->get('SystemFilesystemCheck', function (Request $request, Response $response, array $args): Response {
        $filesystem = AppIntegrityService::getFilesystemPrerequisites();
        AppIntegrityService::reportUnmet($filesystem);

        return SlimUtils::renderJSON($response, $filesystem);
    });

    $group->get('SystemLocaleCheck', function (Request $request, Response $response, array $args): Response {
        $localeInfo = LocaleService::getLocaleSetupInfo();
        if (empty($localeInfo['systemLocaleDetected'])) {
            TelemetryService::captureInstallGaps([], [], ['Server locale']);
        }

        return SlimUtils::renderJSON($response, $localeInfo);
    });

    $postHandler = function (Request $request, Response $response, array $args): Response {
        // Use GLOBALS instead of SystemURLs (Config.php doesn't exist during setup)
        $docRoot = $GLOBALS['CHURCHCRM_SETUP_DOC_ROOT'] ?? dirname(__DIR__, 2);
        $configFile = $docRoot . '/Include/Config.php';
        if (file_exists($configFile)) {
            return $response->withStatus(403, 'Setup is already complete.');
        }

        $setupData = $request->getParsedBody();

        // Validate each field and collect errors
        $errors = [];
        if (!isset($setupData['DB_SERVER_NAME'])) {
            $errors['DB_SERVER_NAME'] = 'Missing DB_SERVER_NAME';
        } elseif (!is_valid_hostname($setupData['DB_SERVER_NAME'])) {
            $errors['DB_SERVER_NAME'] = 'Invalid DB_SERVER_NAME';
        }
        if (!isset($setupData['DB_SERVER_PORT'])) {
            $errors['DB_SERVER_PORT'] = 'Missing DB_SERVER_PORT';
        } elseif (!is_valid_port($setupData['DB_SERVER_PORT'])) {
            $errors['DB_SERVER_PORT'] = 'Invalid DB_SERVER_PORT';
        }
        if (!isset($setupData['DB_NAME'])) {
            $errors['DB_NAME'] = 'Missing DB_NAME';
        } elseif (!is_valid_db_name($setupData['DB_NAME'])) {
            $errors['DB_NAME'] = 'Invalid DB_NAME';
        }
        if (!isset($setupData['DB_USER'])) {
            $errors['DB_USER'] = 'Missing DB_USER';
        } elseif (!is_valid_db_user($setupData['DB_USER'])) {
            $errors['DB_USER'] = 'Invalid DB_USER';
        }
        if (!isset($setupData['DB_PASSWORD'])) {
            $errors['DB_PASSWORD'] = 'Missing DB_PASSWORD';
        } elseif (!is_valid_db_password($setupData['DB_PASSWORD'])) {
            $errors['DB_PASSWORD'] = 'Invalid DB_PASSWORD';
        }
        if (!isset($setupData['ROOT_PATH'])) {
            $errors['ROOT_PATH'] = 'Missing ROOT_PATH';
        } elseif (!is_valid_root_path($setupData['ROOT_PATH'])) {
            $errors['ROOT_PATH'] = 'Invalid ROOT_PATH';
        }
        if (!isset($setupData['URL'])) {
            $errors['URL'] = 'Missing URL';
        } elseif (!URLValidator::isValidConfigURL($setupData['URL'])) {
            $errors['URL'] = 'Invalid URL format';
        }
        if (!empty($errors)) {
            TelemetryService::captureInstallFailure('config_invalid');

            return SlimUtils::renderJSON($response->withStatus(400), ['errors' => $errors]);
        }

        // Use sanitized values
        $dbServerName = sanitize_db_field($setupData['DB_SERVER_NAME']);
        $dbServerPort = preg_replace('/[^0-9]/', '', $setupData['DB_SERVER_PORT']);
        $dbName      = sanitize_db_field($setupData['DB_NAME']);
        $dbUser      = sanitize_db_field($setupData['DB_USER']);
        $dbPassword  = $setupData['DB_PASSWORD'];
        $rootPath    = $setupData['ROOT_PATH'];
        $url         = $setupData['URL'];

        // Prove these exact values before Config.php exists. A failure here leaves setup unlocked.
        $connection = connect_to_database($dbServerName, $dbServerPort, $dbName, $dbUser, $dbPassword);
        if ($connection['reason'] !== null) {
            TelemetryService::captureInstallFailure($connection['reason']);

            return SlimUtils::renderJSON($response, [
                'valid'   => true,
                'correct' => false,
                'reason'  => $connection['reason'],
                'message' => $connection['message'],
            ]);
        }

        // An unsupported server version is not a failed connection: it installs after an acknowledgment.
        $supported = DatabaseVersionService::check($connection['version'])['supported'];
        if (!$supported &&!filter_var($setupData['ACKNOWLEDGE_UNSUPPORTED_DB'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return SlimUtils::renderJSON($response, [
                'valid'     => true,
                'correct'   => true,
                'supported' => false,
                'message'   => DatabaseVersionService::getUnsupportedMessage($connection['version']),
            ]);
        }

        $template = file_get_contents($docRoot . '/Include/Config.php.example');
        $template = strtr($template, [
            '||DB_SERVER_NAME||' => escape_php_single_quoted($dbServerName),
            '||DB_SERVER_PORT||' => escape_php_single_quoted($dbServerPort),
            '||DB_NAME||'        => escape_php_single_quoted($dbName),
            '||DB_USER||'        => escape_php_single_quoted($dbUser),
            '||DB_PASSWORD||'    => escape_php_single_quoted($dbPassword),
            '||ROOT_PATH||'      => escape_php_single_quoted($rootPath),
            '||URL||'            => escape_php_single_quoted($url),
        ]);

        if (file_put_contents($configFile, $template) === false) {
            TelemetryService::captureInstallFailure('config_not_writable');

            return SlimUtils::renderJSON($response, [
                'valid'   => true,
                'correct' => false,
                'reason'  => 'config_not_writable',
                'message' => gettext('ChurchCRM could not save Include/Config.php. Ask your web host to make the Include folder writable.'),
            ]);
        }

        // Generate correct RewriteBase in all routing .htaccess files
        // now that $sRootPath is known. See #10563.
        try {
            \ChurchCRM\Utils\HtaccessGenerator::generateAll($rootPath, $docRoot);
        } catch (\Throwable $e) {
            // Non-fatal during setup
            error_log('HtaccessGenerator failed during setup: ' . $e->getMessage());
        }

        return SlimUtils::renderJSON($response, ['valid' => true, 'correct' => true]);
    };

    $group->post('', $postHandler);
    $group->post('/', $postHandler);
});



// Config.php.example puts every value in a single-quoted PHP string; strtr() also stops a value containing another placeholder from being re-substituted
function escape_php_single_quoted($value)
{
    return addcslashes($value, "'\\");
}

// PDO::ATTR_TIMEOUT only limits opening the socket. A server that accepts it and never
// finishes the handshake is cut off by the read timeout.
function connect_to_database(string $host, string $port, string $name, string $user, string $password): array
{
    $previousReadTimeout = ini_set('mysqlnd.net_read_timeout', '5');

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $name),
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );

        return [
            'version' => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            'reason'  => null,
            'message' => null,
        ];
    } catch (\Throwable $e) {
        $failure = classify_database_connection_failure($e);

        return ['version' => null, 'reason' => $failure['reason'], 'message' => $failure['message']];
    } finally {
        if ($previousReadTimeout !== false) {
            ini_set('mysqlnd.net_read_timeout', $previousReadTimeout);
        }
    }
}

function classify_database_connection_failure(\Throwable $e): array
{
    $code = 0;
    if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
        $code = (int) $e->errorInfo[1];
    } elseif (preg_match('/\[(\d{3,4})\]/', $e->getMessage(), $matches) === 1) {
        $code = (int) $matches[1];
    }

    // 1044 is access denied to that database after the password was accepted. A user granted
    // only their own database gets 1044, not 1049, when the name is wrong.
    $reason = match (true) {
        $code === 1045 => 'database_login_rejected',
        in_array($code, [1044, 1049], true) => 'database_not_found',
        in_array($code, [2001, 2002, 2003, 2005, 2006, 2013], true) => 'database_host_unreachable',
        default => 'database_unreachable',
    };

    return ['reason' => $reason, 'message' => database_connection_message($reason)];
}

function database_connection_message(string $reason): string
{
    return match ($reason) {
        'database_host_unreachable' => gettext('ChurchCRM cannot reach the database server at that address and port. Check the host name and the port. On shared hosting the host is often not localhost.'),
        'database_login_rejected' => gettext('The database server rejected that username or password.'),
        'database_not_found' => gettext('The database server accepted the login, but there is no database with that name.'),
        default => gettext('ChurchCRM could not connect to the database with these settings.'),
    };
}

function sanitize_db_field($value)
{
    // Allow only letters, numbers, underscore, dash, dot, colon, and @
    return preg_replace('/[^a-zA-Z0-9_\-\.:\@]/', '', $value);
}

// Hostname: letters, numbers, dash, dot, no @ or :
function is_valid_hostname($value)
{
    // Hostnames: RFC 1123, allow a-z, 0-9, dash, dot, no @ or :
    return preg_match('/^(?=.{1,253}$)([a-zA-Z0-9\-]{1,63}\.)*[a-zA-Z0-9\-]{1,63}$/', $value);
}

// DB name: letters, numbers, underscore, dash, dot
function is_valid_db_name($value)
{
    return preg_match('/^[a-zA-Z0-9_\-\.]+$/', $value);
}

// DB user: allow @ (for Azure), letters, numbers, underscore, dash, dot
function is_valid_db_user($value)
{
    return preg_match('/^[a-zA-Z0-9_\-\.@]+$/', $value);
}

// DB password: allow anything except empty string
function is_valid_db_password($value)
{
    return strlen($value) > 0;
}
function is_valid_port($value)
{
    return preg_match('/^[0-9]{1,5}$/', $value) && (int)$value > 0 && (int)$value < 65536;
}

function is_valid_root_path($value)
{
    // Allow empty string OR a path starting with / (no trailing slash)
    return preg_match('#^(|\/[a-zA-Z0-9_\-\.\/]*)$#', $value);
}
