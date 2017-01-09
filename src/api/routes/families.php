<?php

use ChurchCRM\FamilyQuery;
use ChurchCRM\Token;
use ChurchCRM\Note;
use ChurchCRM\Emails\FamilyVerificationEmail;
use ChurchCRM\TokenQuery;

$app->group('/families', function () {
    $this->get('/search/{query}', function ($request, $response, $args) {
        $query = $args['query'];
        echo $this->FamilyService->getFamiliesJSON($this->FamilyService->search($query));
    });

    $this->get('/lastedited', function ($request, $response, $args) {
        $this->FamilyService->lastEdited();
    });

    $this->get('/byCheckNumber/{scanString}', function ($request, $response, $args) {
        $scanString = $args['scanString'];
        echo $this->FinancialService->getMemberByScanString($scanString);
    });

    $this->get('/byEnvelopeNumber/{envelopeNumber:[0-9]+}', function ($request, $response, $args) {
        $envelopeNumber = $args['envelopeNumber'];
        echo $this->FamilyService->getFamilyStringByEnvelope($envelopeNumber);
    });

  $this->post('/{familyId}/verify', function ($request, $response, $args) {
    $familyId = $args["familyId"];
    $family = FamilyQuery::create()->findPk($familyId);
    if ($family != null) {
      TokenQuery::create()->filterByType("verifyFamily")->filterByReferenceId($family->getId())->delete();
      $token = new Token();
      $token->build("verifyFamily", $family->getId());
      $token->save();
      $email = new FamilyVerificationEmail($family->getEmails(), $family->getName(), $token->getToken());
      if ($email->send()) {
        $response = $response->withStatus(200);
      } else {
        $response = $response->withStatus(404)->getBody()->write($email->getError());
      }
    } else {
      $response = $response->withStatus(404)->getBody()->write("familyId: " . $familyId . " not found");
    }
    return $response;
  });

  $this->post('/verify/{familyId}/now', function ($request, $response, $args) {
    $familyId = $args["familyId"];
    $family = FamilyQuery::create()->findPk($familyId);
    if ($family != null) {
      $note = new Note();
      $note->setFamId($family->getId());
      $note->setText(gettext("Family Data Verified"));
      $note->setType("verify");
      $note->setEntered($_SESSION['user']->getId());
      $note->save();
      $response = $response->withStatus(200);
    } else {
      $response = $response->withStatus(404)->getBody()->write("familyId: " . $familyId . " not found");
    }
    return $response;
  });
});
