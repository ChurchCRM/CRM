<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;


$app->group('/', function (RouteCollectorProxy $group): void {
    $group->get('', function (Request $request, Response $response, array $args): Response {
        $renderer = new PhpRenderer('templates/');
        $renderPage = 'setup-steps.php';
        if (version_compare(phpversion(), '8.1.0', '<')) {
            $renderPage = 'setup-error.php';
        }

        return $renderer->render($response, $renderPage, ['sRootPath' => SystemURLs::getRootPath()]);
    });

    $group->get('SystemIntegrityCheck', function (Request $request, Response $response, array $args): Response {
        $AppIntegrity = AppIntegrityService::verifyApplicationIntegrity();

        return SlimUtils::renderStringJSON($response, $AppIntegrity['status']);
    });

    $group->get('SystemPrerequisiteCheck', function (Request $request, Response $response, array $args): Response {
        $required = AppIntegrityService::getApplicationPrerequisites();

        return SlimUtils::renderJSON($response, $required);
    });

    $group->post('', function (Request $request, Response $response, array $args): Response {
        $configFile = SystemURLs::getDocumentRoot() . '/Include/Config.php';
        if (file_exists($configFile)) {
            return $response->withStatus(403, 'Setup is already complete.');
        }

        $setupData = $request->getParsedBody();

        // Validate each field
        if (
            !isset($setupData['DB_SERVER_NAME'], $setupData['DB_SERVER_PORT'], $setupData['DB_NAME'], $setupData['DB_USER'], $setupData['DB_PASSWORD'], $setupData['ROOT_PATH'], $setupData['URL']) ||
            !is_valid_db_field($setupData['DB_SERVER_NAME']) ||
            !is_valid_port($setupData['DB_SERVER_PORT']) ||
            !is_valid_db_field($setupData['DB_NAME']) ||
            !is_valid_db_field($setupData['DB_USER']) ||
            !is_valid_db_field($setupData['DB_PASSWORD']) ||
            !is_valid_root_path($setupData['ROOT_PATH']) ||
            !filter_var($setupData['URL'], FILTER_VALIDATE_URL)
        ) {
            return $response->withStatus(400, 'Invalid setup data.');
        }

        // Use sanitized values
        $dbServerName = sanitize_db_field($setupData['DB_SERVER_NAME']);
        $dbServerPort = preg_replace('/[^0-9]/', '', $setupData['DB_SERVER_PORT']);
        $dbName      = sanitize_db_field($setupData['DB_NAME']);
        $dbUser      = sanitize_db_field($setupData['DB_USER']);
        $dbPassword  = sanitize_db_field($setupData['DB_PASSWORD']);
        $rootPath    = $setupData['ROOT_PATH'];
        $url         = $setupData['URL'];

        $template = file_get_contents(SystemURLs::getDocumentRoot() . '/Include/Config.php.example');
        $template = str_replace('||DB_SERVER_NAME||', $dbServerName, $template);
        $template = str_replace('||DB_SERVER_PORT||', $dbServerPort, $template);
        $template = str_replace('||DB_NAME||', $dbName, $template);
        $template = str_replace('||DB_USER||', $dbUser, $template);
        $template = str_replace('||DB_PASSWORD||', $dbPassword, $template);
        $template = str_replace('||ROOT_PATH||', $rootPath, $template);
        $template = str_replace('||URL||', $url, $template);

        file_put_contents($configFile, $template);

        return $response->withStatus(200);
    });
});


function sanitize_db_field($value) {
    // Allow only letters, numbers, underscore, dash, dot, colon, and @
    return preg_replace('/[^a-zA-Z0-9_\-\.:\@]/', '', $value);
}

function is_valid_db_field($value) {
    return preg_match('/^[a-zA-Z0-9_\-\.:\@]+$/', $value);
}

function is_valid_port($value) {
    return preg_match('/^[0-9]{1,5}$/', $value) && (int)$value > 0 && (int)$value < 65536;
}

function is_valid_root_path($value) {
    return preg_match('#^\/[a-zA-Z0-9_\-\.\/]*$#', $value);
}