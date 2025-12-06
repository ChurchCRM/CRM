<?php

use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Utils\PhpVersion;
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
            if (version_compare(phpversion(), PhpVersion::getRequiredPhpVersion(), '<')) {
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
        } elseif (!filter_var($setupData['URL'], FILTER_VALIDATE_URL)) {
            $errors['URL'] = 'Invalid URL';
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
        $template = str_replace('||DB_SERVER_NAME||', $dbServerName, $template);
        $template = str_replace('||DB_SERVER_PORT||', $dbServerPort, $template);
        $template = str_replace('||DB_NAME||', $dbName, $template);
        $template = str_replace('||DB_USER||', $dbUser, $template);
        $template = str_replace('||DB_PASSWORD||', $dbPassword, $template);
        $template = str_replace('||ROOT_PATH||', $rootPath, $template);
        $template = str_replace('||URL||', $url, $template);

        file_put_contents($configFile, $template);

        return $response->withStatus(200);
    };

    $group->post('', $postHandler);
    $group->post('/', $postHandler);
});



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

