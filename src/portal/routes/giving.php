<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\DateTimeUtils;
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

    $gifts = [];
    $total = 0.0;
    // A pledge row has no donor. Only a household adult receives the family's gifts.
    if ($isHousehold) {
        foreach (
            PledgeQuery::create()
                ->filterForTaxReport($year . '-01-01', $year . '-12-31', [], [(int) $family->getId()], [])
                ->orderById()
                ->find() as $pledge
        ) {
            $amount = (float) $pledge->getAmount();
            $total += $amount;
            $gifts[] = [
                'date' => DateTimeUtils::formatDate((string) $pledge->getDate('Y-m-d')),
                'fund' => $pledge->getDonationFund() ? $pledge->getDonationFund()->getName() : '',
                'method' => (string) $pledge->getMethod(),
                'amount' => CurrencyFormatter::format($amount),
            ];
        }
    }


    $letterheadPath = SystemConfig::getValue('bDirLetterHead');
    $useGraphic = is_string($letterheadPath) && $letterheadPath !== '' && is_readable($letterheadPath);
    $start = DateTimeUtils::formatDate($year . '-01-01');
    $end = DateTimeUtils::formatDate($year . '-12-31');
    $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';

    $deductible = $total;
    $data = [
        'letterhead' => $useGraphic ? 'graphic' : 'address',
        'letterhead_image' => $useGraphic ? $letterheadPath : null,
        'title' => gettext('Contribution Statement'),
        'date_range' => sprintf(gettext('%s through %s'), $start, $end),
        'opening' => trim(SystemConfig::getValue('sTaxReport1') . ' ' . $start . ' – ' . $end . '.'),
        'closing' => SystemConfig::getValue('sTaxReport2'),
        'questions' => SystemConfig::getValue('sTaxReport3'),
        'signer' => SystemConfig::getValue('sTaxSigner'),
        'disclosure' => gettext('No goods or services were provided in exchange for these contributions, other than intangible religious benefits.'),
        'empty_message' => gettext('No contributions were recorded for this period.'),
        'families' => [[
            'salutation' => $isHousehold ? $family->getName() : $actor->getFullName(),
            'address' => $family->getMailingAddressLines(),
            'total' => CurrencyFormatter::format($total),
            'goods_services' => CurrencyFormatter::format(0),
            'deductible' => CurrencyFormatter::format($deductible),
            'gifts' => $gifts,
        ]],
    ];

    $pdf = (new MpdfRenderer())->renderToString('tax-report', $data);
    $response->getBody()->write($pdf);
    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', $disposition . '; filename="giving-statement-' . $year . '.pdf"');
});
