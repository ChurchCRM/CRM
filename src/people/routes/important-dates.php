<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ImportantDateType;
use ChurchCRM\model\ChurchCRM\ImportantDateTypeQuery;
use ChurchCRM\Service\ImportantDateService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Any staff user reads important dates, the same as reading people and families.
$app->get('/important-dates', function (Request $request, Response $response): Response {
    $windows = [7, 30, 90];
    $params = $request->getQueryParams();
    $days = (int) ($params['days'] ?? 30);
    if (!in_array($days, $windows, true)) {
        $days = 30;
    }

    $types = iterator_to_array(ImportantDateTypeQuery::create()->filterByActive(true)->orderForDisplay()->find(), false);
    $typeIds = array_map(static fn (ImportantDateType $type): int => (int) $type->getId(), $types);
    $typeId = (int) ($params['type'] ?? 0);
    if (!in_array($typeId, $typeIds, true)) {
        $typeId = 0;
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'important-dates.php', [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('Important Dates'),
        'sPageSubtitle'      => gettext('Baptisms, dedications and other yearly dates coming up for people and families'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Important Dates')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Manage Date Types'), 'url' => '/admin/people/important-dates', 'icon' => 'fa-sliders'],
        ]),
        'windows'            => $windows,
        'days'               => $days,
        'types'              => $types,
        'typeId'             => $typeId,
        'hasAnyType'         => ImportantDateTypeQuery::create()->exists(),
        'isAdmin'            => AuthenticationManager::getCurrentUser()->isAdmin(),
        'rows'               => (new ImportantDateService())->upcoming($days, $typeId > 0 ? [$typeId] : []),
    ]);
});
