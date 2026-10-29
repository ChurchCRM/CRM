<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\GivingStatementService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Member-facing year-end statement.
// A household adult sees the family's gifts. Anyone else sees an empty
// statement: a pledge row does not record which person gave.

$portalGivingActor = static function (Request $request): ?Person {
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    return $person instanceof Person ? $person : null;
};

$group->get('/giving', function (Request $request, Response $response) use ($portalGivingActor): Response {
    $actor = $portalGivingActor($request);
    $family = $actor?->getFamily();
    $currentYear = (int) date('Y');
    $firstYear = min($currentYear, (int) ($family?->getDateEntered('Y') ?: $currentYear));
    $isHousehold = $family && PortalSelfService::isAdultOf($family, $actor);
    $givingYears = [];
    if ($family && $isHousehold) {
        foreach (PledgeQuery::create()->filterByFamId((int) $family->getId())->filterByPledgeOrPayment('Payment')->find() as $gift) {
            $givingYears[(int) $gift->getDate('Y')] = true;
        }
    }
    $years = [];
    for ($year = $currentYear; $year >= $firstYear; --$year) {
        $years[] = ['value' => $year, 'hasGiving' => isset($givingYears[$year])];
    }
    $year = max($firstYear, $currentYear - 1);
    return PortalTwig::render(
        $response,
        'giving/index.html.twig',
        [
            'pageTitle' => gettext('My Giving'),
            'year' => $year,
            'isHousehold' => $isHousehold,
            'years' => $years,
        ],
        PortalNav::GIVING
    );
});

$group->get('/giving/statement', function (Request $request, Response $response) use ($portalGivingActor): Response {
    $actor = $portalGivingActor($request);
    if (!$actor || !$actor->getFamily()) {
        return PortalTwig::render(
            $response,
            'family/none.html.twig',
            [
                'pageTitle' => gettext('You are not currently associated with a family'),
                'officeEmail' => trim(ChurchMetaData::getChurchEmail()),
                'officePhone' => trim(ChurchMetaData::getChurchPhone()),
                'officePhoneHref' => preg_replace('/[^0-9+]/', '', ChurchMetaData::getChurchPhone()),
            ],
            PortalNav::GIVING
        );
    }

    $year = (int) ($request->getQueryParams()['year'] ?? (date('Y') - 1));
    if ($year < 1990 || $year > (int) date('Y')) {
        $year = (int) date('Y') - 1;
    }

    $family = $actor->getFamily();
    $isHousehold = PortalSelfService::isAdultOf($family, $actor);

    // A pledge row has no donor. Only a household adult receives the family's gifts.
    $statements = $isHousehold
        ? GivingStatementService::families(GivingStatementService::paymentsByFamily(
            GivingStatementService::criteriaFromQuery(['familyId' => $family->getId(), 'year' => $year])
        ))
        : [];
    if ($statements === []) {
        $statements = [GivingStatementService::emptyStatement($family, $isHousehold ? $family->getName() : $actor->getFullName())];
    }

    $data = GivingStatementService::document($statements, $year . '-01-01', $year . '-12-31', 'graphic');
    $disposition = GivingStatementService::contentDisposition(GivingStatementService::fileLabel($year, $family->getName()));

    $pdf = (new MpdfRenderer())->renderToString('tax-report', $data);
    $response->getBody()->write($pdf);
    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', $disposition);
});
