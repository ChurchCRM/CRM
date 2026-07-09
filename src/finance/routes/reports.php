<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports', function (RouteCollectorProxy $group): void {

    // Financial Reports selection page (migrated from FinancialReports.php)
    $group->get('', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Financial Reports'),
            'sPageSubtitle' => gettext('Generate reports for tax statements, pledge tracking, and financial analysis.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Reports')],
            ]),
        ];
        
        return $renderer->render($response, 'reports.php', $pageArgs);
    });

    // Tax Year Report (Giving Report) configuration
    $group->get('/tax-statements', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Tax Statements (Giving Report)'),
            'sPageSubtitle' => gettext('Generate annual giving statements for tax purposes'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Reports'), '/finance/reports'],
                [gettext('Tax Statements')],
            ]),
            'iFYMonth' => SystemConfig::getValue('iFYMonth'),
        ];
        
        return $renderer->render($response, 'reports/tax-statements.php', $pageArgs);
    });

});
