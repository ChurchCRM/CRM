<?php

use ChurchCRM\Authentication\AuthenticationManager;
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

// Reports -> People Reports: the people queries of the frozen Query View
// (issue #9995) rebuilt as an MVC page on Propel. Admin only, like QueryList.php.
$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->get('', 'getPeopleReportsIndex');
    $group->get('/', 'getPeopleReportsIndex');
    $group->get('/{slug:[a-z-]+}', 'getPeopleReport');
    $group->get('/{slug:[a-z-]+}/csv', 'getPeopleReportCsv');
})->add(AdminRoleAuthMiddleware::class);

/**
 * @return array<int, array{href: string, title: string, description: string}>
 */
function peopleReportLinks(): array
{
    $user = AuthenticationManager::getCurrentUser();
    $links = [
        [
            'href' => '/DirectoryReports.php',
            'title' => gettext('People Directory'),
            'description' => gettext('Printable directory of all people, grouped by family'),
        ],
    ];
    if ($user->isMenuOptionsEnabled()) {
        $links[] = [
            'href' => '/LettersAndLabels.php',
            'title' => gettext('Letters & Mailing Labels'),
            'description' => gettext('Generate letters and mailing labels'),
        ];
    }

    return $links;
}

function getPeopleReportsIndex(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'reports-index.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('People Reports'),
        'sPageSubtitle' => gettext('Birthdays, anniversaries, volunteers and other people lists with a classification filter'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People Reports')],
        ]),
        'reports' => (new PeopleReportService())->getReports(),
        'links' => peopleReportLinks(),
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
    $reportParams = peopleReportQueryParams($report, $values);

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'report.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => $report['name'],
        'sPageSubtitle' => $report['description'],
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People Reports'), '/people/reports'],
            [$report['name']],
        ]),
        'slug' => $slug,
        'report' => $report,
        'values' => $values,
        'missing' => $missing,
        'options' => $options,
        'rows' => $rows,
        'reportParams' => $reportParams,
        'csvQuery' => http_build_query($reportParams),
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
 * The resolved parameters as query-string values, so the CSV link and the
 * labels run the same report the page shows.
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
