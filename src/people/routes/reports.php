<?php

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DirectoryReportService;
use ChurchCRM\Service\PeopleReportService;
use ChurchCRM\Slim\Middleware\Request\Auth\StaffRoleAuthMiddleware;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Reports -> People Reports: the people queries of the frozen Query View
// (issue #9995) rebuilt as an MVC page on Propel. Open to every staff user, who can
// already read and export the person list (#9003).
$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->get('', 'getPeopleReportsIndex');
    $group->get('/', 'getPeopleReportsIndex');
    $group->get('/{slug:[a-z-]+}', 'getPeopleReport');
    $group->get('/{slug:[a-z-]+}/csv', 'getPeopleReportCsv');
    $group->post('/directory', 'postPeopleDirectory');
})->add(StaffRoleAuthMiddleware::class);

/**
 * @return array<int, array{href: string, title: string, description: string}>
 */
function peopleReportLinks(): array
{
    return [
        [
            'href' => '/DirectoryReports.php',
            'title' => gettext('People Directory'),
            'description' => gettext('Printable directory of all people, grouped by family'),
        ],
        [
            'href' => '/LettersAndLabels.php',
            'title' => gettext('Letters & Mailing Labels'),
            'description' => gettext('Generate letters and mailing labels'),
        ],
    ];
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
 * The church directory PDF. The options form is DirectoryReports.php; the group's staff
 * middleware keeps self-service accounts out.
 */
function postPeopleDirectory(Request $request, Response $response): Response
{
    $post = (array) $request->getParsedBody();
    $cartDirectory = array_key_exists('cartdir', $post);

    $classifications = [];
    foreach ((array) ($post['sDirClassifications'] ?? []) as $classification) {
        // 'int' filtering turns junk into 0, which would quietly select Unassigned.
        $valid = is_scalar($classification) && ctype_digit(trim((string) $classification));
        if (!$valid && !$cartDirectory) {
            return peopleDirectoryBadRequest($response);
        }
        if ($valid) {
            $classifications[] = InputUtils::filterInt($classification);
        }
    }
    // The browser omits an empty multi-select, and no qualifier would print everyone
    // (the cart directory is limited by the cart instead).
    if ($classifications === [] && !$cartDirectory) {
        return peopleDirectoryBadRequest($response);
    }

    $ids = fn (string $key): array => array_values(array_map(
        fn ($value): int => InputUtils::filterInt($value),
        array_filter((array) ($post[$key] ?? []), 'is_scalar')
    ));
    $flag = fn (string $key): bool => isset($post[$key]);

    $show = [
        'address' => $flag('bDirAddress'),
        'mailing' => $flag('bDirAddress') && $flag('bDirMailingAddress'),
        'wedding' => $flag('bDirWedding'),
        'birthday' => $flag('bDirBirthday'),
        'familyPhone' => $flag('bDirFamilyPhone'),
        'familyWork' => $flag('bDirFamilyWork'),
        'familyCell' => $flag('bDirFamilyCell'),
        'familyEmail' => $flag('bDirFamilyEmail'),
        'personalPhone' => $flag('bDirPersonalPhone'),
        'personalWork' => $flag('bDirPersonalWork'),
        'personalCell' => $flag('bDirPersonalCell'),
        'personalEmail' => $flag('bDirPersonalEmail'),
        'personalWorkEmail' => $flag('bDirPersonalWorkEmail'),
        'photo' => $flag('bDirPhoto'),
    ];
    $customFieldOrders = [];
    foreach (array_keys($post) as $name) {
        if (preg_match('/^bCustom(\d+)$/', (string) $name, $match) === 1) {
            $customFieldOrders[] = (int) $match[1];
        }
    }

    $text = fn (string $key, int $length = 255): string => mb_substr(trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', is_scalar($post[$key] ?? null) ? (string) $post[$key] : '')), 0, $length);
    $paper = $text('PageSize');

    $pdf = (new DirectoryReportService())->pdf([
        'classifications' => $classifications === [] ? null : $classifications,
        'groups' => $ids('GroupID'),
        'cartIds' => $cartDirectory ? array_map('intval', array_filter(explode(',', Cart::getCartIdString()))) : null,
        'excludeInactive' => $flag('bExcludeInactive'),
        'headRoles' => $ids('sDirRoleHead'),
        'spouseRoles' => $ids('sDirRoleSpouse'),
        'show' => $show,
        'customFieldOrders' => $customFieldOrders,
    ], [
        'paper' => in_array($paper, ['letter', 'a4'], true) ? $paper : 'legal',
        'booklet' => $text('sDirLayout') === 'booklet',
        'columns' => InputUtils::filterInt($post['NumCols'] ?? '1') ?: 1,
        'fontSize' => InputUtils::filterInt($post['FSize'] ?? '8') ?: 8,
        'titlePage' => $flag('bDirUseTitlePage'),
        'title' => [
            'name' => $text('sChurchName'),
            'address' => $text('sChurchAddress'),
            'city' => $text('sChurchCity'),
            'state' => $text('sChurchState'),
            'zip' => $text('sChurchZip'),
            'phone' => $text('sChurchPhone'),
            'disclaimer' => $text('sDirectoryDisclaimer', 2000),
        ],
    ]);

    return PdfResponse::create($response, $pdf, 'Directory-' . date(SystemConfig::getValue('sDateFilenameFormat')));
}

function peopleDirectoryBadRequest(Response $response): Response
{
    $response->getBody()->write(gettext('Select at least one classification to include in the directory.'));

    return $response->withStatus(400)->withHeader('Content-Type', 'text/plain; charset=UTF-8');
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
        'submitted' => $request->getQueryParams() !== [],
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
