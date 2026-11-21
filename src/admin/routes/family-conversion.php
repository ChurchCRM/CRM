<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/people/convert-to-families', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $unassigned = (new FamilyService())->unassignedPeopleQuery();
    $next = (clone $unassigned)->findOne();
    $query = $request->getQueryParams();
    $convertedFamily = isset($query['family']) ? FamilyQuery::create()->findPk((int) $query['family']) : null;

    return $renderer->render($response, 'family-conversion.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Convert Individuals to Families'),
        'sPageSubtitle' => gettext('Convert individual records into family units'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('People Settings'), '/admin/people'],
            [gettext('Convert Individuals to Families')],
        ]),
        'sPageHeaderButtons' => PageHeader::peopleSettingsButton(),
        'familyConversion' => [
            'unassignedCount' => $unassigned->count(),
            'next' => $next === null ? null : [
                'id' => (int) $next->getId(),
                'name' => $next->getFullName(),
                'url' => $next->getViewURI(),
            ],
            'convertedFamily' => $convertedFamily === null ? null : [
                'name' => (string) $convertedFamily->getName(),
                'url' => $convertedFamily->getViewURI(),
            ],
            'convertedCount' => isset($query['converted']) ? max(0, (int) $query['converted']) : null,
            'csrfToken' => CSRFUtils::generateToken(),
        ],
    ]);
});
