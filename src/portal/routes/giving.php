<?php

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
            'pageTitle' => gettext('My giving statement'),
            'year' => $year,
            'isHousehold' => $actor && $actor->getFamily() && PortalSelfService::isAdultOf($actor->getFamily(), $actor),
        ],
        PortalNav::GIVING
    );
});

$group->get('/giving/statement', function (Request $request, Response $response) use ($portalGivingActor): Response {
    $actor = $portalGivingActor($request);
    if (!$actor || !$actor->getFamily()) {
        return PortalTwig::render($response, 'family/none.html.twig', ['pageTitle' => gettext('My giving statement')]);
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


    $data = [
        'letterhead' => 'address',
        'date_range' => sprintf(gettext('Giving statement for %s'), (string) $year),
        'closing' => SystemConfig::getValue('sTaxReport2'),
        'signer' => SystemConfig::getValue('sTaxSigner'),
        'families' => [[
            'salutation' => $isHousehold ? $family->getName() : $actor->getFullName(),
            'address' => $family->getAddress(),
            'total' => CurrencyFormatter::format($total),
            'gifts' => $gifts,
        ]],
    ];

    $pdf = (new MpdfRenderer())->renderToString('tax-report', $data);
    $response->getBody()->write($pdf);
    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', 'inline; filename="giving-statement-' . $year . '.pdf"');
});
