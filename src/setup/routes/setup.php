<?php

use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;

$app->group('/', function () {
    $this->get('', function ($request, $response, $args) {
        $renderer = new PhpRenderer('templates/');

        return $renderer->render($response, 'setup-steps.php', ['sRootPath' => SystemURLs::getRootPath()]);
    });

    $this->get('SystemIntegrityCheck', function ($request, $response, $args) {
        $AppIntegrity = ChurchCRM\Service\AppIntegrityService::verifyApplicationIntegrity();
        echo $AppIntegrity['status'];
    });

    $this->get('SystemPrerequisiteCheck', function ($request, $response, $args) {
        $required = ChurchCRM\Service\AppIntegrityService::getApplicationPrerequisites();
        return $response->withStatus(200)->withJson($required);
    });

});




/*// don't depend on autoloader here, just in case validation doesn't pass.
if (!(file_exists('ChurchCRM/dto/SystemURLs.php') && file_exists('ChurchCRM/Service/AppIntegrityService.php'))) {
    echo gettext("One or more required setup files are missing.  Please verify you downloaded the correct ChurchCRM package");
    exit;
}*/
/*
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;

if (isset($_POST['Setup'])) {
    $template = file_get_contents('Include/Config.php.example');
    $template = str_replace('||DB_SERVER_NAME||', $_POST['DB_SERVER_NAME'], $template);
    $template = str_replace('||DB_NAME||', $_POST['DB_NAME'], $template);
    $template = str_replace('||DB_USER||', $_POST['DB_USER'], $template);
    $template = str_replace('||DB_PASSWORD||', $_POST['DB_PASSWORD'], $template);
    $template = str_replace('||ROOT_PATH||', $_POST['ROOT_PATH'], $template);
    $template = str_replace('||URL||', $_POST['URL'], $template);
    file_put_contents('Include/Config.php', $template);
    header('Location: index.php');
    exit();
}


*/
