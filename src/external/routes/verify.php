<?php

use Slim\Views\PhpRenderer;
use ChurchCRM\FamilyQuery;
use ChurchCRM\PersonQuery;

$app->group('/verify', function () {

    $this->get('/{token}', function ($request, $response, $args) {
        $token = $args['token'];
        $renderer = new PhpRenderer("templates/verify/");
        $family = FamilyQuery::create()->findPk($token);
        if ($family != null) {
            return $renderer->render($response, "verify-family-info.php", array("family" => $family));
        } else {
            return $renderer->render($response, "enter-info.php", array("token" => $token));
        }
    });

    $this->post('/', function ($request, $response, $args) {
        $body = $request->getParsedBody();
        $renderer = new PhpRenderer("templates/verify/");
        $family = PersonQuery::create()->findByEmail($body["email"]);
        return $renderer->render($response, "view-info.php", array("family" => $family));
    });
});


