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
        $setupData = $request->getParsedBody();

        $template = file_get_contents(SystemURLs::getDocumentRoot() . '/Include/Config.php.example');


        $template = str_replace('||DB_SERVER_NAME||', $setupData['DB_SERVER_NAME'], $template);
        $template = str_replace('||DB_SERVER_PORT||', $setupData['DB_SERVER_PORT'], $template);
        $template = str_replace('||DB_NAME||', $setupData['DB_NAME'], $template);
        $template = str_replace('||DB_USER||', $setupData['DB_USER'], $template);
        $template = str_replace('||DB_PASSWORD||', $setupData['DB_PASSWORD'], $template);
        $template = str_replace('||ROOT_PATH||', $setupData['ROOT_PATH'], $template);
        $template = str_replace('||URL||', $setupData['URL'], $template);

        file_put_contents(SystemURLs::getDocumentRoot() . '/Include/Config.php', $template);

        return $response->withStatus(200);
    });
});
