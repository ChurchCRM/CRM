<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use Slim\Views\PhpRenderer;

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
        echo $AppIntegrity['status'];
    });

    $app->get('SystemPrerequisiteCheck', function ($request, $response, $args) {
        $required = AppIntegrityService::getApplicationPrerequisites();

        return $response->withJson($required);
    });

    $app->post('', function ($request, $response, $args) {
        $setupDate = $request->getParsedBody();
        $template = file_get_contents(SystemURLs::getDocumentRoot() . '/Include/Config.php.example');

        $template = str_replace('||DB_SERVER_NAME||', $setupDate['DB_SERVER_NAME'], $template);
        $template = str_replace('||DB_SERVER_PORT||', $setupDate['DB_SERVER_PORT'], $template);
        $template = str_replace('||DB_NAME||', $setupDate['DB_NAME'], $template);
        $template = str_replace('||DB_USER||', $setupDate['DB_USER'], $template);
        $template = str_replace('||DB_PASSWORD||', $setupDate['DB_PASSWORD'], $template);
        $template = str_replace('||ROOT_PATH||', $setupDate['ROOT_PATH'], $template);
        $template = str_replace('||URL||', $setupDate['URL'], $template);

        file_put_contents(SystemURLs::getDocumentRoot() . '/Include/Config.php', $template);

        return $response->withStatus(200);
    });
});
