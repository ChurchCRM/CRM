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
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Slim\Request\SlimUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/families', function (RouteCollectorProxy $group): void {
    $group->get('/latest', 'getLatestFamilies');
    $group->get('/updated', 'getUpdatedFamilies');
    $group->get('/anniversaries', 'getFamiliesWithAnniversaries');

    $group->get('/email/without', function (Request $request, Response $response, array $args): Response {
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
                    $familiesWithoutEmails[] = $family->toArray();
                }
            }
        }

        return SlimUtils::renderJSON($response, ['count' => count($familiesWithoutEmails), 'families' => $familiesWithoutEmails]);
    });

    $group->get('/search/{query}', function (Request $request, Response $response, array $args): Response {
        $query = $args['query'];
        $results = [];
        $q = FamilyQuery::create()
            ->filterByName("%$query%", Criteria::LIKE)
            ->limit(15)
            ->find();
        foreach ($q as $family) {
            $results[] = $family->toSearchArray();
        }

        return SlimUtils::renderJSON($response, ['Families' => $results]);
    });

    $group->get('/self-register', function (Request $request, Response $response, array $args): Response {
        $families = FamilyQuery::create()
            ->filterByEnteredBy(Person::SELF_REGISTER)
            ->orderByDateEntered(Criteria::DESC)
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $families->toArray()]);
    });

    $group->get('/self-verify', function (Request $request, Response $response, array $args): Response {
        $verificationNotes = NoteQuery::create()
            ->filterByEnteredBy(Person::SELF_VERIFY)
            ->orderByDateEntered(Criteria::DESC)
            ->joinWithFamily()
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $verificationNotes->toArray()]);
    });

    $group->get('/pending-self-verify', function (Request $request, Response $response, array $args): Response {
        $pendingTokens = TokenQuery::create()
            ->filterByType(Token::TYPE_FAMILY_VERIFY)
            ->filterByRemainingUses(['min' => 1])
            ->filterByValidUntilDate(['min' => new DateTime()])
            ->addJoin(TokenTableMap::COL_REFERENCE_ID, FamilyTableMap::COL_FAM_ID)
            ->withColumn(FamilyTableMap::COL_FAM_NAME, 'FamilyName')
            ->withColumn(TokenTableMap::COL_REFERENCE_ID, 'FamilyId')
            ->limit(100)
            ->find();

        return SlimUtils::renderJSON($response, ['families' => $pendingTokens->toArray()]);
    });

    $group->get('/byCheckNumber/{scanString}', function (Request $request, Response $response, array $args): Response {
        $scanString = $args['scanString'];

        /** @var FinancialService $financialService */
        $financialService = $this->get('FinancialService');

        return SlimUtils::renderJSON($response, $financialService->getMemberByScanString($scanString));
    });

    /**
     * Update the family status to activated or deactivated with :familyId and :status true/false.
     * Pass true to activate and false to deactivate.     *.
     */
    $group->post('/{familyId:[0-9]+}/activate/{status}', function (Request $request, Response $response, array $args): Response {
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

        return SlimUtils::renderJSON($response, ['success' => true]);
    });
});

function getFamiliesWithAnniversaries(Request $request, Response $response, array $args): Response
{
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->filterByWeddingdate(null, Criteria::NOT_EQUAL)
        ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'MONTH(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') =' . date('m'), Criteria::CUSTOM)
        ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'DAY(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') =' . date('d'), Criteria::CUSTOM)
        ->orderByWeddingdate('DESC')
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedFamilies($families, false, false, true));
}

function getLatestFamilies(Request $request, Response $response, array $args): Response
{
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->orderByDateEntered('DESC')
        ->limit(10)
        ->find();

    return SlimUtils::renderJSON($response, buildFormattedFamilies($families, true, false, false));
}

function getUpdatedFamilies(Request $request, Response $response, array $args): Response
{
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->orderByDateLastEdited('DESC')
        ->limit(10)
        ->find();

    $formattedList = buildFormattedFamilies($families, false, true, false);

    return SlimUtils::renderJSON($response, $formattedList);
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

        $formattedList[] = $formattedFamily;
    }

    return ['families' => $formattedList];
}
