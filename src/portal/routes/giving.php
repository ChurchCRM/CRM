<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\GivingStatementService;
use ChurchCRM\Utils\FiscalYearUtils;
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
    $today = date('Y-m-d');
    $currentYear = FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId());
    $entered = $family?->getDateEntered('Y-m-d') ?: $today;
    $firstYear = min($currentYear, FiscalYearUtils::getEndYearForDate($entered));
    $isHousehold = $family && PortalSelfService::isAdultOf($family, $actor);
    $givingYears = [];
    if ($family && $isHousehold) {
        foreach (PledgeQuery::create()->filterByFamId((int) $family->getId())->filterByPledgeOrPayment('Payment')->find() as $gift) {
            $givingYears[FiscalYearUtils::getEndYearForDate($gift->getDate('Y-m-d'))] = true;
        }
    }
    $years = [];
    for ($year = $currentYear; $year >= $firstYear; --$year) {
        $years[] = [
            'value' => $year,
            'label' => FiscalYearUtils::getFiscalYearDatesForCalendarYear($year)['label'],
            'hasGiving' => isset($givingYears[$year]),
        ];
    }
    $year = max($firstYear, FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId() - 1));
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

    $currentEndYear = FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId());
    $previousEndYear = FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId() - 1);
    $year = (int) ($request->getQueryParams()['year'] ?? $previousEndYear);
    if ($year < 1990 || $year > $currentEndYear) {
        $year = $previousEndYear;
    }

    $family = $actor->getFamily();
    $isHousehold = PortalSelfService::isAdultOf($family, $actor);
    $criteria = GivingStatementService::criteriaFromQuery(['familyId' => $family->getId(), 'year' => $year]);

    // A pledge row has no donor. Only a household adult receives the family's gifts.
    $statements = $isHousehold
        ? GivingStatementService::families(GivingStatementService::paymentsByFamily($criteria))
        : [];
    if ($statements === []) {
        $statements = [GivingStatementService::emptyStatement($family, $isHousehold ? $family->getName() : $actor->getFullName())];
    }

    $data = GivingStatementService::document($statements, $criteria['dateStart'], $criteria['dateEnd']);
    $disposition = GivingStatementService::contentDisposition(GivingStatementService::fileLabel($year, $family->getName()));

    $pdf = (new MpdfRenderer())->renderToString('tax-report', $data);
    $response->getBody()->write($pdf);
    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', $disposition);
});
