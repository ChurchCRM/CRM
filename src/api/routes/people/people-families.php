<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\MenuEventsCount;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Map\TokenTableMap;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/families', function (RouteCollectorProxy $group) {
    $group->get('/latest', 'getLatestFamilies');
    $group->get('/updated', 'getUpdatedFamilies');
    $group->get('/anniversaries', 'getFamiliesWithAnniversaries');

    $group->get('/email/without', function ($request, $response, $args) {
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

        return $response->withJson(['count' => count($familiesWithoutEmails), 'families' => $familiesWithoutEmails]);
    });

    $group->get(
        '/numbers',
        fn ($request, $response, $args) => $response->withJson(MenuEventsCount::getNumberAnniversaries())
    );

    $group->get('/search/{query}', function ($request, $response, $args) {
        $query = $args['query'];
        $results = [];
        $q = FamilyQuery::create()
            ->filterByName("%$query%", Criteria::LIKE)
            ->limit(15)
            ->find();
        foreach ($q as $family) {
            array_push($results, $family->toSearchArray());
        }

        return $response->withJson(json_encode(['Families' => $results], JSON_THROW_ON_ERROR));
    });

    $group->get('/self-register', function ($request, $response, $args) {
        $families = FamilyQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();

        return $response->withJson(['families' => $families->toArray()]);
    });

    $group->get('/self-verify', function ($request, $response, $args) {
        $verificationNotes = NoteQuery::create()
            ->filterByEnteredBy(Person::SELF_VERIFY)
            ->orderByDateEntered(Criteria::DESC)
            ->joinWithFamily()
            ->limit(100)
            ->find();

        return $response->withJson(['families' => $verificationNotes->toArray()]);
    });

    $group->get('/pending-self-verify', function ($request, $response, $args) {
        $pendingTokens = TokenQuery::create()
            ->filterByType(Token::TYPE_FAMILY_VERIFY)
            ->filterByRemainingUses(['min' => 1])
            ->filterByValidUntilDate(['min' => new DateTime()])
            ->addJoin(TokenTableMap::COL_REFERENCE_ID, FamilyTableMap::COL_FAM_ID)
            ->withColumn(FamilyTableMap::COL_FAM_NAME, 'FamilyName')
            ->withColumn(TokenTableMap::COL_REFERENCE_ID, 'FamilyId')
            ->limit(100)
            ->find();

        return $response->withJson(['families' => $pendingTokens->toArray()]);
    });

    $group->get('/byCheckNumber/{scanString}', function ($request, $response, $args) use ($app) {
        $scanString = $args['scanString'];
        echo $app->FinancialService->getMemberByScanString($scanString);
    });

    /**
     * Update the family status to activated or deactivated with :familyId and :status true/false.
     * Pass true to activate and false to deactivate.     *.
     */
    $group->post('/{familyId:[0-9]+}/activate/{status}', function ($request, $response, $args) {
        $familyId = $args['familyId'];
        $newStatus = $args['status'];

        $family = FamilyQuery::create()->findPk($familyId);
        $currentStatus = (empty($family->getDateDeactivated()) ? 'true' : 'false');

        //update only if the value is different
        if ($currentStatus != $newStatus) {
            if ($newStatus == 'false') {
                $family->setDateDeactivated(date('YmdHis'));
            } elseif ($newStatus == 'true') {
                $family->setDateDeactivated(null);
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
            $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
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
        ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'MONTH('.FamilyTableMap::COL_FAM_WEDDINGDATE.') ='.date('m'), Criteria::CUSTOM)
        ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'DAY('.FamilyTableMap::COL_FAM_WEDDINGDATE.') ='.date('d'), Criteria::CUSTOM)
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

function buildFormattedFamilies($families, bool $created, bool $edited, bool $wedding): array
{
    $formattedList = [];

    foreach ($families as $family) {
        $formattedFamily = [];
        $formattedFamily['FamilyId'] = $family->getId();
        $formattedFamily['Name'] = $family->getName();
        $formattedFamily['Address'] = $family->getAddress();
        if ($created) {
            $value = null;
            if ($family->getDateEntered()) {
                $value = date_format($family->getDateEntered(), SystemConfig::getValue('sDateFormatLong'));
            }
            $formattedFamily['Created'] = $value;
        }

        if ($edited) {
            $value = null;
            if ($family->getDateLastEdited()) {
                $value = date_format($family->getDateLastEdited(), SystemConfig::getValue('sDateFormatLong'));
            }
            $formattedFamily['LastEdited'] = $value;
        }

        if ($wedding) {
            $value = null;
            if ($family->getWeddingdate()) {
                $value = date_format($family->getWeddingdate(), SystemConfig::getValue('sDateFormatLong'));
            }
            $formattedFamily['WeddingDate'] = $value;
        }

        array_push($formattedList, $formattedFamily);
    }

    return ['families' => $formattedList];
}
