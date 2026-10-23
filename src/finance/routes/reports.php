<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports', function (RouteCollectorProxy $group): void {


    // Proof of concept: shared letterhead via mPDF (framework only)
    $group->get('/pdf-framework-preview', function (Request $request, Response $response): Response {
        $renderer = new \ChurchCRM\Service\MpdfRenderer();
        $church = [
            'name' => \ChurchCRM\dto\SystemConfig::getValue('sChurchName'),
            'address' => \ChurchCRM\dto\SystemConfig::getValue('sChurchAddress'),
            'city' => \ChurchCRM\dto\SystemConfig::getValue('sChurchCity'),
            'state' => \ChurchCRM\dto\SystemConfig::getValue('sChurchState'),
            'zip' => \ChurchCRM\dto\SystemConfig::getValue('sChurchZip'),
            'phone' => \ChurchCRM\dto\SystemConfig::getValue('sChurchPhone'),
            'email' => \ChurchCRM\dto\SystemConfig::getValue('sChurchEmail'),
        ];
        $data = [
            'letterhead' => 'address',
            'church' => $church,
            'date_range' => gettext('Year-end statements preview'),
            'closing' => \ChurchCRM\dto\SystemConfig::getValue('sTaxReport2'),
            'signer' => \ChurchCRM\dto\SystemConfig::getValue('sTaxSigner'),
            'families' => [[
                'salutation' => gettext('Sample Family'),
                'address' => "123 Example St\nAnytown, ST 00000",
                'total' => '$0.00',
                'gifts' => [[
                    'date' => date('Y-m-d'),
                    'fund' => gettext('General'),
                    'method' => gettext('Check'),
                    'amount' => '$0.00',
                ]],
            ]],
        ];
        $pdf = $renderer->renderToString('tax-report', $data);
        $response->getBody()->write($pdf);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="pdf-framework-preview.pdf"');
    });

    // Financial Reports selection page (migrated from FinancialReports.php)
    $group->get('', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Financial Reports'),
            'sPageSubtitle' => gettext('Generate reports for tax statements, pledge tracking, and financial analysis.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports')],
            ]),
        ];
        
        return $renderer->render($response, 'reports.php', $pageArgs);
    });

});
