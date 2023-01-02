<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\FamilyQuery;
use ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\Map\TokenTableMap;
use ChurchCRM\Note;
use ChurchCRM\NoteQuery;
use ChurchCRM\Person;
use ChurchCRM\Token;
use ChurchCRM\TokenQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/families', function () {

    $this->get('/latest', 'getLatestFamilies');
    $this->get('/updated', 'getUpdatedFamilies');
    $this->get('/anniversaries', 'getFamiliesWithAnniversaries');

    $this->get('/email/without', function ($request, $response, $args) {
        $families = FamilyQuery::create()->joinWithPerson()->find();

        $familiesWithoutEmails = [];
        foreach ($families as $family) {
            if (empty($family->getEmail())) {
                $hasEmail = false;
                foreach ($family->getPeopleSorted() as $person) {
                    if (!empty($person->getEmail() || !empty($person->getWorkEmail()))) {
                        $hasEmail = true;
                        break;
                    }
                }
                if (!$hasEmail) {
                    array_push($familiesWithoutEmails, $family->toArray());
                }
             }
        }

        return $response->withJson(["count" => count($familiesWithoutEmails), "families" => $familiesWithoutEmails]);
    });

    $this->get('/numbers', function ($request, $response, $args) {
        return $response->withJson(MenuEventsCount::getNumberAnniversaries());
    });


    $this->get('/search/{query}', function ($request, $response, $args) {
        $query = $args['query'];
        $results = [];
        $q = FamilyQuery::create()
            ->filterByName("%$query%", Criteria::LIKE)
            ->limit(15)
            ->find();
        foreach ($q as $family) {
            array_push($results, $family->toSearchArray());
        }

        return $response->withJson(json_encode(["Families" => $results]));
    });

    $this->get('/self-register', function ($request, $response, $args) {
        $families = FamilyQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();
        return $response->withJson(['families' => $families->toArray()]);
    });

    $this->get('/self-verify', function ($request, $response, $args) {
        $verifcationNotes = NoteQuery::create()
            ->filterByEnteredBy(Person::SELF_VERIFY)
            ->orderByDateEntered(Criteria::DESC)
            ->joinWithFamily()
            ->limit(100)
            ->find();
        return $response->withJson(['families' => $verifcationNotes->toArray()]);
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
        return $response->withJson(['families' => $pendingTokens->toArray()]);
    });


    $this->get('/byCheckNumber/{scanString}', function ($request, $response, $args) {
        $scanString = $args['scanString'];
        echo $this->FinancialService->getMemberByScanString($scanString);
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
            $note->setEntered(AuthenticationManager::GetCurrentUser()->getId());
            $note->save();
        }
        return $response->withJson(['success' => true]);

    });

});


function getFamiliesWithAnniversaries(Request $request, Response $response, array $p_args)
{
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->filterByWeddingdate(null, Criteria::NOT_EQUAL)
        ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE,"MONTH(". FamilyTableMap::COL_FAM_WEDDINGDATE .") =" . date('m'),Criteria::CUSTOM)
        ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE,"DAY(". FamilyTableMap::COL_FAM_WEDDINGDATE .") =" . date('d'),Criteria::CUSTOM)
        ->orderByWeddingdate('DESC')
        ->find();

    return $response->withJson(buildFormattedFamilies($families, false, false, true));

}
function getLatestFamilies(Request $request, Response $response, array $p_args)
{
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->orderByDateEntered('DESC')
        ->limit(10)
        ->find();

    return $response->withJson(buildFormattedFamilies($families, true, false, false));
}

function getUpdatedFamilies(Request $request, Response $response, array $p_args)
{
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->orderByDateLastEdited('DESC')
        ->limit(10)
        ->find();

    $formattedList = buildFormattedFamilies($families, false, true, false);

    return $response->withJson($formattedList);
}

function buildFormattedFamilies($families, $created, $edited, $wedding)
{
    $formattedList = [];

    foreach ($families as $family) {
        $formattedFamily = [];
        $formattedFamily["FamilyId"] = $family->getId();
        $formattedFamily["Name"] = $family->getName();
        $formattedFamily["Address"] = $family->getAddress();
        if ($created) {
            $formattedFamily["Created"] = date_format($family->getDateEntered(), SystemConfig::getValue('sDateFormatLong'));
        }

        if ($edited) {
            $formattedFamily["LastEdited"] = date_format($family->getDateLastEdited(), SystemConfig::getValue('sDateFormatLong'));
        }

        if ($wedding) {
            $formattedFamily["WeddingDate"] = date_format($family->getWeddingdate(), SystemConfig::getValue('sDateFormatLong'));
        }

        array_push($formattedList, $formattedFamily);
    }
    return ["families" => $formattedList];
}
