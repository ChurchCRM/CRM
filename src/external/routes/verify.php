<?php

use Slim\Views\PhpRenderer;
use ChurchCRM\FamilyQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\TokensQuery;

$app->group('/verify', function () {

    $this->get('/{token}', function ($request, $response, $args) {
        $renderer = new PhpRenderer("templates/verify/");
        $token = TokensQuery::create()->findPk($args['token']);
        if ($token != null && $token->isVerifyToken()) {
            $family = FamilyQuery::create()->findPk($token->getReferenceId());
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


