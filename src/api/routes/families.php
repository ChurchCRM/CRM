<?php

// Routes
use ChurchCRM\FamilyQuery;
use ChurchCRM\Token;
use ChurchCRM\Note;
use ChurchCRM\Emails\FamilyVerificationEmail;
use ChurchCRM\TokenQuery;
use ChurchCRM\Person;
use Propel\Runtime\ActiveQuery\Criteria;

$app->group('/families', function () {
  
     $this->get('/{familyId:[0-9]+}', function($request, $response, $args)  {
        $family = FamilyQuery::create()->findPk($args['familyId']);
        return $response->withJSON($family->toJSON());
    });
  
    $this->get('/search/{query}', function ($request, $response, $args) {
        $query = $args['query'];
        $results = [];
        $q = FamilyQuery::create()
            ->filterByName("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
            ->limit(15)
            ->find();
        foreach ($q as $family)
        {
          array_push($results,$family->toSearchArray());
        }

       return $response->withJSON(json_encode(["Families"=>$results]));
    });

    $this->get('/self-register', function($request, $response, $args)  {
        $families = FamilyQuery::create()
            ->filterByEnteredBy(Person::$SELF_REGISTER)
            ->find();
        return $response->withJSON(['families' => $families->toArray()]);
    });

    $this->get('/byCheckNumber/{scanString}', function ($request, $response, $args) {
        $scanString = $args['scanString'];
        echo $this->FinancialService->getMemberByScanString($scanString);
    });

    $this->get('/{familyId:[0-9]+}/photo', function($request, $response, $args)  {
        $family = FamilyQuery::create()->findPk($args['familyId']);
        if ( $family->isPhotoLocal() )
        {
            return $response->write($family->getPhotoBytes());
        }
        else
        {
            return $response->withStatus(404);
        }
    });

    $this->get('/{familyId:[0-9]+}/thumbnail', function($request, $response, $args)  {
        $family = FamilyQuery::create()->findPk($args['familyId']);
        if ( $family->isPhotoLocal())
        {
            return $response->write($family->getThumbnailBytes())->withHeader('Content-type', $family->getPhotoContentType());
        }
        else
        {
            return $response->withStatus(404);
        }
    });

    $this->post('/{familyId:[0-9]+}/photo', function($request, $response, $args)  {
        $input = (object)$request->getParsedBody();
        $family = FamilyQuery::create()->findPk($args['familyId']);
        $family->setImageFromBase64($input->imgBase64);

        $response->withJSON(array("status"=>"success","upload"=>$upload));
    });

    $this->delete('/{familyId:[0-9]+}/photo', function($request, $response, $args)  {
        $family = FamilyQuery::create()->findPk($args['familyId']);
        return json_encode(array("status"=>$family->deletePhoto()));
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
                $this->Logger->error($email->getError());
                throw new \Exception($email->getError());
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

    /**
     * Update the family status to activated or deactivated with :familyId and :status true/false.
     * Pass true to activate and false to deactivate.     *
     */
    $this->post('/{familyId:[0-9]+}/activate/{status}', function ($request, $response, $args) {
        $familyId = $args["familyId"];
        $newStatus = $args["status"];

        $family = FamilyQuery::create()->findPk($familyId);
        $currentStatus = (empty($family->getDateDeactivated()) ? 'true' : 'false');

        //update only if the value is different
        if($currentStatus != $newStatus) {
            if ($newStatus == "false") {
                $family->setDateDeactivated(date('YmdHis'));
            } elseif ($newStatus == "true") {
                $family->setDateDeactivated(Null);
            }
            $family->save();

            //Create a note to record the status change
            $note = new Note();
            $note->setFamId($familyId);
            if($newStatus == 'false') {
                $note->setText(gettext('Deactivated the Family'));
            }
            else {
                $note->setText(gettext('Activated the Family'));
            }
            $note->setType('edit');
            $note->setEntered($_SESSION['iUserID']);
            $note->save();
        }
        return $response->withJson(['success'=> true]);

    });

});
