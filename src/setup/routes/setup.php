<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->group('/', function () use ($app) {
    $app->get('', function ($request, $response, $args) {
        $renderer = new PhpRenderer('templates/');
        $renderPage = 'setup-steps.php';
        if (version_compare(phpversion(), '8.1.0', '<')) {
            $renderPage = 'setup-error.php';
        }

        return $renderer->render($response, $renderPage, ['sRootPath' => SystemURLs::getRootPath()]);
    });

    $app->get('SystemIntegrityCheck', function ($request, $response, $args) {
        $AppIntegrity = AppIntegrityService::verifyApplicationIntegrity();
        $response->getBody()->write(json_encode($AppIntegrity['status']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('SystemPrerequisiteCheck', function ($request, $response, $args) {
        $required = AppIntegrityService::getApplicationPrerequisites();
        $response->getBody()->write(json_encode($required));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('', function (Request $request, Response $response, array $args) {
        $setupData = $request->getParsedBody();

        $template = file_get_contents(SystemURLs::getDocumentRoot().'/Include/Config.php.example');

        $template = str_replace('||DB_SERVER_NAME||', $setupData['DB_SERVER_NAME'], $template);
        $template = str_replace('||DB_SERVER_PORT||', $setupData['DB_SERVER_PORT'], $template);
        $template = str_replace('||DB_NAME||', $setupData['DB_NAME'], $template);
        $template = str_replace('||DB_USER||', $setupData['DB_USER'], $template);
        $template = str_replace('||DB_PASSWORD||', $setupData['DB_PASSWORD'], $template);
        $template = str_replace('||ROOT_PATH||', $setupData['ROOT_PATH'], $template);
        $template = str_replace('||URL||', $setupData['URL'], $template);

        file_put_contents(SystemURLs::getDocumentRoot().'/Include/Config.php', $template);

        return $response->withStatus(200);
    });
});
