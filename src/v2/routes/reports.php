<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\PeopleReportService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Data/Reports -> People Reports: the people queries of the frozen Query View
// (issue #9995) rebuilt as an MVC page on Propel. Admin only, like QueryList.php.
$app->group('/reports/people', function (RouteCollectorProxy $group): void {
    $group->get('', 'getPeopleReportsIndex');
    $group->get('/', 'getPeopleReportsIndex');
    $group->get('/{slug:[a-z-]+}', 'getPeopleReport');
    $group->get('/{slug:[a-z-]+}/csv', 'getPeopleReportCsv');
})->add(AdminRoleAuthMiddleware::class);

function getPeopleReportsIndex(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/reports/');

    return $renderer->render($response, 'people-index.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('People Reports'),
        'sPageSubtitle' => gettext('Birthdays, anniversaries, volunteers and other people lists with a classification filter'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Data/Reports')],
            [gettext('People Reports')],
        ]),
        'reports' => (new PeopleReportService())->getReports(),
    ]);
}

/**
 * @return array{service: PeopleReportService, slug: string, report: array, values: array, missing: string[]}
 */
function resolvePeopleReport(Request $request, array $args): array
{
    $service = new PeopleReportService();
    $slug = (string) $args['slug'];
    $report = $service->getReport($slug);
    if ($report === null) {
        throw new HttpNotFoundException($request, gettext('Report not found'));
    }
    $resolved = $service->resolveParams($slug, $request->getQueryParams());

    return ['service' => $service, 'slug' => $slug, 'report' => $report] + $resolved;
}

function getPeopleReport(Request $request, Response $response, array $args): Response
{
    ['service' => $service, 'slug' => $slug, 'report' => $report, 'values' => $values, 'missing' => $missing] = resolvePeopleReport($request, $args);

    $options = [];
    foreach ($report['params'] as $key => $param) {
        $options[$key] = $service->getOptionsForType($param['type']);
    }

    $rows = $missing === [] ? $service->run($slug, $values) : null;

    $renderer = new PhpRenderer('templates/reports/');

    return $renderer->render($response, 'people-report.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => $report['name'],
        'sPageSubtitle' => $report['description'],
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Data/Reports')],
            [gettext('People Reports'), '/v2/reports/people'],
            [$report['name']],
        ]),
        'slug' => $slug,
        'report' => $report,
        'values' => $values,
        'missing' => $missing,
        'options' => $options,
        'rows' => $rows,
        'csvQuery' => http_build_query(peopleReportQueryParams($report, $values)),
    ]);
}

function getPeopleReportCsv(Request $request, Response $response, array $args): Response
{
    ['service' => $service, 'slug' => $slug, 'report' => $report, 'values' => $values, 'missing' => $missing] = resolvePeopleReport($request, $args);

    $rows = $missing === [] ? $service->run($slug, $values) : [];

    $exporter = new CsvExporter();
    $exporter->insertHeaders(array_values($report['columns']));
    foreach ($rows as $row) {
        $line = [];
        foreach (array_keys($report['columns']) as $column) {
            $line[] = (string) ($row[$column] ?? '');
        }
        $exporter->insertRow($line);
    }

    $response->getBody()->write($exporter->getContent());

    return $response
        ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->withHeader('Content-Disposition', 'attachment; filename="people-report-' . $slug . '.csv"')
        ->withHeader('Cache-Control', 'no-store');
}

/**
 * The resolved parameters as query-string values, so the CSV link runs the
 * same report the page shows.
 *
 * @return array<string, mixed>
 */
function peopleReportQueryParams(array $report, array $values): array
{
    $params = [];
    foreach (array_keys($report['params']) as $key) {
        $value = $values[$key] ?? null;
        if ($value === null || $value === []) {
            continue;
        }
        $params[$key] = $value;
    }

    return $params;
}
