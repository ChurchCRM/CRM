<?php

/* Contributors Philippe Logel */

// Routes
use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\Emails\FamilyVerificationEmail;
use ChurchCRM\FamilyQuery;
use ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\Map\TokenTableMap;
use ChurchCRM\Note;
use ChurchCRM\NoteQuery;
use ChurchCRM\Person;
use ChurchCRM\Token;
use ChurchCRM\TokenQuery;
use Propel\Runtime\ActiveQuery\Criteria;

$app->group('/families', function () {


    $this->get('/numbers', function ($request, $response, $args) {
        return $response->withJson(MenuEventsCount::getNumberAnniversaries());
    });


    $this->get('/search/{query}', function ($request, $response, $args) {
        $query = $args['query'];
        $results = [];
        $q = FamilyQuery::create()
            ->filterByName("%$query%", Propel\Runtime\ActiveQuery\Criteria::LIKE)
            ->limit(15)
            ->find();
        foreach ($q as $family) {
            array_push($results, $family->toSearchArray());
        }

        return $response->withJSON(json_encode(["Families" => $results]));
    });

    $this->get('/self-register', function ($request, $response, $args) {
        $families = FamilyQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();
        return $response->withJSON(['families' => $families->toArray()]);
    });

    $this->get('/self-verify', function ($request, $response, $args) {
        $verifcationNotes = NoteQuery::create()
            ->filterByEnteredBy(Person::SELF_VERIFY)
            ->orderByDateEntered(Criteria::DESC)
            ->joinWithFamily()
            ->limit(100)
            ->find();
        return $response->withJSON(['families' => $verifcationNotes->toArray()]);
    });

    $this->get('/pending-self-verify', function ($request, $response, $args) {
        $pendingTokens = TokenQuery::create()
            ->filterByType(Token::typeFamilyVerify)
            ->filterByRemainingUses(array('min' => 1))
            ->filterByValidUntilDate(array('min' => new DateTime()))
            ->addJoin(TokenTableMap::COL_REFERENCE_ID, FamilyTableMap::COL_FAM_ID)
            ->withColumn(FamilyTableMap::COL_FAM_NAME, "FamilyName")
            ->withColumn(TokenTableMap::COL_REFERENCE_ID, "FamilyId")
            ->limit(100)
            ->find();
        return $response->withJSON(['families' => $pendingTokens->toArray()]);
    });


    $this->get('/byCheckNumber/{scanString}', function ($request, $response, $args) {
        $scanString = $args['scanString'];
        echo $this->FinancialService->getMemberByScanString($scanString);
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
                $family->createTimeLineNote("verify-link");
                $response = $response->withStatus(200);
            } else {
                $this->Logger->error($email->getError());
                throw new \Exception($email->getError());
            }
        } else {
            $response = $response->withStatus(404, gettext("FamilyId") . " " . $familyId . " " . gettext("not found"));
        }
        return $response;
    });

    $this->post('/verify/{familyId}/now', function ($request, $response, $args) {
        $familyId = $args["familyId"];
        $family = FamilyQuery::create()->findPk($familyId);
        if ($family != null) {
            $family->verify();
            $response = $response->withStatus(200);
        } else {
            $response = $response->withStatus(404, gettext("FamilyId"). ": " . $familyId . " ". gettext("not found"));
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
        if ($currentStatus != $newStatus) {
            if ($newStatus == "false") {
                $family->setDateDeactivated(date('YmdHis'));
            } elseif ($newStatus == "true") {
                $family->setDateDeactivated(Null);
            }
            $family->save();

            //Create a note to record the status change
            $note = new Note();
            $note->setFamId($familyId);
            if ($newStatus == 'false') {
                $note->setText(gettext('Deactivated the Family'));
            } else {
                $note->setText(gettext('Activated the Family'));
            }
            $note->setType('edit');
            $note->setEntered($_SESSION['user']->getId());
            $note->save();
        }
        return $response->withJson(['success' => true]);

    });



});
