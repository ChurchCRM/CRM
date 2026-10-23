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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Member-facing year-end statement.
// Head of household and spouse see the household gifts.
// Everyone else sees only gifts credited to them.

$portalGivingActor = static function (Request $request): ?Person {
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    return $person instanceof Person ? $person : null;
};

$group->get('/giving', function (Request $request, Response $response) use ($portalGivingActor): Response {
    $actor = $portalGivingActor($request);
    $year = (int) date('Y') - 1;
    return PortalTwig::render(
        $response,
        'giving/index.html.twig',
        [
            'pageTitle' => gettext('My Giving'),
            'year' => $year,
            'isHousehold' => $actor && $actor->getFamily() && PortalSelfService::isAdultOf($actor->getFamily(), $actor),
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

    $query = PledgeQuery::create()
        ->filterForTaxReport($year . '-01-01', $year . '-12-31', [], [(int) $family->getId()], [])
        ->find();

    $gifts = [];
    $total = 0.0;
    foreach ($query as $pledge) {
        if (!$isHousehold && (int) $pledge->getPersonId() !== (int) $actor->getId()) {
            continue;
        }
        $amount = (float) $pledge->getAmount();
        $total += $amount;
        $gifts[] = [
            'date' => (string) $pledge->getDate('Y-m-d'),
            'fund' => $pledge->getDonationFund() ? $pledge->getDonationFund()->getName() : '',
            'method' => (string) $pledge->getMethod(),
            'amount' => CurrencyFormatter::format($amount),
        ];
    }


    $deductible = $total;
    $data = [
        'letterhead' => 'address',
        'title' => gettext('Contribution Statement'),
        'date_range' => sprintf(gettext('%s through %s'), $year . '-01-01', $year . '-12-31'),
        'opening' => trim(SystemConfig::getValue('sTaxReport1') . ' ' . $year . '.'),
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
        ->withHeader('Content-Disposition', 'inline; filename="giving-statement-' . $year . '.pdf"');
});
