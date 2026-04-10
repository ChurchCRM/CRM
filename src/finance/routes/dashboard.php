<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Match /finance root path - Finance Dashboard
$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Finance Dashboard'),
        'sPageSubtitle' => gettext('Manage donations, pledges, and financial records'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Finance')],
        ]),
        'sSettingsCollapseId' => 'financialSettings',
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Financial Settings'), 'icon' => 'fa-cog', 'collapse' => '#financialSettings'],
            ['label' => gettext('Donation Funds'), 'url' => '/DonationFundEditor.php', 'icon' => 'fa-hand-holding-dollar'],
            ['label' => gettext('Manage Envelopes'), 'url' => '/ManageEnvelopes.php', 'icon' => 'fa-envelope'],
        ]),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});
