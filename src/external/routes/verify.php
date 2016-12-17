<?php

use Slim\Views\PhpRenderer;
use ChurchCRM\FamilyQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\TokenQuery;
use ChurchCRM\dto\SystemURLs;

$app->group('/verify', function () {

    $this->get('/{token}', function ($request, $response, $args) {
        $renderer = new PhpRenderer("templates/verify/");
        $token = TokenQuery::create()->findPk($args['token']);
        $haveFamily = false;
        if ($token != null && $token->isVerifyToken() && $token->isValid()) {
          $family = FamilyQuery::create()->findPk($token->getReferenceId());
          $haveFamily = ($family != null);
        }

        if ($haveFamily) {
            return $renderer->render($response, "verify-family-info.php", array("family" => $family));
        } else {
            // return $renderer->render($response, "enter-info.php", array("token" => $token));
          return $response->withStatus(302)->withHeader('Location', SystemURLs::getURL());
        }
    });

    /*$this->post('/', function ($request, $response, $args) {
        $body = $request->getParsedBody();
        $renderer = new PhpRenderer("templates/verify/");
        $family = PersonQuery::create()->findByEmail($body["email"]);
        return $renderer->render($response, "view-info.php", array("family" => $family));
    });*/
});


