<?php

use Slim\Views\PhpRenderer;
use ChurchCRM\FamilyQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\TokenQuery;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Note;
use ChurchCRM\Person;

$app->group('/verify', function () {

  $this->get('/{token}', function ($request, $response, $args) {
    $renderer = new PhpRenderer("templates/verify/");
    $token = TokenQuery::create()->findPk($args['token']);
    $haveFamily = false;
    if ($token != null && $token->isVerifyFamilyToken() && $token->isValid()) {
      $family = FamilyQuery::create()->findPk($token->getReferenceId());
      $haveFamily = ($family != null);
      if ($token->getRemainingUses() > 0) {
        $token->setRemainingUses($token->getRemainingUses() - 1);
        $token->save();
      }
    }

    if ($haveFamily) {
      return $renderer->render($response, "verify-family-info.php", array("family" => $family, "token" => $token));
    } else {
      return $renderer->render($response, "/../404.php", array("message" => gettext("Unable to load verification info")));
    }
  });

  $this->post('/{token}', function ($request, $response, $args) {
    $token = TokenQuery::create()->findPk($args['token']);
    if ($token != null && $token->isVerifyFamilyToken() && $token->isValid()) {
      $family = FamilyQuery::create()->findPk($token->getReferenceId());
      if ($family != null) {
        $body = (object) $request->getParsedBody();
        $note = new Note();
        $note->setFamily($family);
        $note->setType("verify");
        $note->setEntered(Person::SELF_VERIFY);
        $note->setText(gettext("No Changes"));
        if (!empty($body->message)) {
          $note->setText($body->message);
        }
        $note->save();
      }
    }
    return $response->withStatus(200);
  });

  /*$this->post('/', function ($request, $response, $args) {
      $body = $request->getParsedBody();
      $renderer = new PhpRenderer("templates/verify/");
      $family = PersonQuery::create()->findByEmail($body["email"]);
      return $renderer->render($response, "view-info.php", array("family" => $family));
  });*/
});


