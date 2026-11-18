<?php

use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\DatabaseVersionService;
use ChurchCRM\Service\LocaleService;
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

        return SlimUtils::renderJSON($response, $required);
    });

    $group->get('SystemFilesystemCheck', function (Request $request, Response $response, array $args): Response {
        $filesystem = AppIntegrityService::getFilesystemPrerequisites();

        return SlimUtils::renderJSON($response, $filesystem);
    });

    $group->get('SystemLocaleCheck', function (Request $request, Response $response, array $args): Response {
        $localeInfo = LocaleService::getLocaleSetupInfo();

        return SlimUtils::renderJSON($response, $localeInfo);
    });

    $group->post('DatabaseVersionCheck', function (Request $request, Response $response, array $args): Response {
        $docRoot = $GLOBALS['CHURCHCRM_SETUP_DOC_ROOT'] ?? dirname(__DIR__, 2);
        if (file_exists($docRoot . '/Include/Config.php')) {
            return $response->withStatus(403, 'Setup is already complete.');
        }

        $setupData = $request->getParsedBody();
        if (
            !isset($setupData['DB_SERVER_NAME'], $setupData['DB_SERVER_PORT'], $setupData['DB_USER'], $setupData['DB_PASSWORD'])
            || !is_valid_hostname($setupData['DB_SERVER_NAME'])
            || !is_valid_port($setupData['DB_SERVER_PORT'])
            || !is_valid_db_user($setupData['DB_USER'])
            || !is_valid_db_password($setupData['DB_PASSWORD'])
        ) {
            return SlimUtils::renderJSON($response, ['error' => 'Invalid database connection settings'], 400);
        }

        $serverVersion = read_db_server_version(
            sanitize_db_field($setupData['DB_SERVER_NAME']),
            preg_replace('/[^0-9]/', '', $setupData['DB_SERVER_PORT']),
            sanitize_db_field($setupData['DB_USER']),
            $setupData['DB_PASSWORD']
        );

        // A server that cannot be reached or read is not warned about, like DatabaseVersionService itself
        $check = DatabaseVersionService::check($serverVersion);
        $message = $check['supported'] ? null : DatabaseVersionService::getUnsupportedMessage($serverVersion);

        return SlimUtils::renderJSON($response, [
            'supported' => $check['supported'],
            'engine'    => $check['engine'],
            'version'   => $check['version'],
            'message'   => $message,
        ]);
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

        file_put_contents($configFile, $template);

        // Generate correct RewriteBase in all routing .htaccess files
        // now that $sRootPath is known. See #10563.
        try {
            \ChurchCRM\Utils\HtaccessGenerator::generateAll($rootPath, $docRoot);
        } catch (\Throwable $e) {
            // Non-fatal during setup
            error_log('HtaccessGenerator failed during setup: ' . $e->getMessage());
        }

        return $response->withStatus(200);
    };

    $group->post('', $postHandler);
    $group->post('/', $postHandler);
});



// Config.php.example puts every value in a single-quoted PHP string; strtr() also stops a value containing another placeholder from being re-substituted
function escape_php_single_quoted($value)
{
    return addcslashes($value, "'\\");
}

// The server version reported on a connection made with the entered credentials, or null when it cannot be read.
// No database name is used, so a database that does not exist yet does not hide the version.
// PDO::ATTR_TIMEOUT only limits opening the socket; a server that accepts it and never sends its
// handshake is cut off by the mysqlnd read timeout.
function read_db_server_version(string $host, string $port, string $user, string $password): ?string
{
    $previousReadTimeout = ini_set('mysqlnd.net_read_timeout', '5');

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s', $host, $port),
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );

        return (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch (\Throwable $e) {
        return null;
    } finally {
        if ($previousReadTimeout !== false) {
            ini_set('mysqlnd.net_read_timeout', $previousReadTimeout);
        }
    }
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
