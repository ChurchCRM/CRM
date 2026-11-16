<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\CsvExportService;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Views\PhpRenderer;

$app->get('/export', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Export'),
        'sPageSubtitle' => gettext('Export your church data to various formats'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Export')],
        ]),
    ];

    return $renderer->render($response, 'export.php', $pageArgs);
});

$app->get('/export/csv', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'csv-export.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('CSV Export'),
        'sPageSubtitle' => gettext('Export data to CSV format for external applications'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Export'), '/admin/export'],
            [gettext('CSV Export')],
        ]),
        'options' => CsvExportService::formOptions(),
        'source' => ($request->getQueryParams()['Source'] ?? '') === 'cart' ? 'cart' : 'filters',
    ]);
});

$app->post('/export/csv', function (Request $request, Response $response): Response {
    $input = (array) $request->getParsedBody();
    $format = strtolower((string) ($input['Format'] ?? ''));
    $service = new CsvExportService();

    try {
        if ($format === 'addtocart') {
            $service->addToCart($input);

            return $response->withHeader('Location', SystemURLs::getRootPath() . '/people/cart')->withStatus(302);
        }
        ['headers' => $headers, 'rows' => $rows] = $service->export($input);
    } catch (InvalidArgumentException $e) {
        throw new HttpBadRequestException($request, $e->getMessage());
    }

    $exporter = new CsvExporter();
    $exporter->insertHeaders($headers);
    $exporter->insertRows($rows);
    $response->getBody()->write($exporter->getContent());

    return $response
        ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->withHeader('Content-Disposition', 'attachment; filename="churchcrm-export-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv"')
        ->withHeader('Cache-Control', 'no-store');
});
