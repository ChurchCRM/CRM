<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\ZeroGiversService;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports/zero-givers', function (RouteCollectorProxy $group): void {
    $page = static function (Response $response, array $criteria, string $message = ''): Response {
        return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'reports/zero-givers.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Zero Givers'),
            'sPageSubtitle' => gettext('Identify members who have not made any donations within a date range.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Zero Givers')],
            ]),
            'criteria' => $criteria,
            'message' => $message,
        ]);
    };

    $group->get('', function (Request $request, Response $response) use ($page): Response {
        return $page($response, ZeroGiversService::criteriaFromQuery($request->getQueryParams()));
    });

    $group->map(['GET', 'POST'], '/download', function (Request $request, Response $response) use ($page): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $criteria = ZeroGiversService::criteriaFromQuery($input);
        $families = ZeroGiversService::families($criteria['dateStart'], $criteria['dateEnd']);
        if ($families === []) {
            return $page($response, $criteria, sprintf(
                gettext('Every active member family made a payment between %s and %s.'),
                DateTimeUtils::formatDate($criteria['dateStart']),
                DateTimeUtils::formatDate($criteria['dateEnd'])
            ));
        }

        if (($input['output'] ?? 'pdf') === 'csv') {
            [$headers, $rows] = ZeroGiversService::csv($families);
            CsvExporter::create($headers, $rows, 'ZeroGivers', 'UTF-8', true);

            return $response;
        }

        $document = ZeroGiversService::document($families, $criteria['dateStart'], $criteria['dateEnd'], $criteria['letterhead']);
        $pdf = (new MpdfRenderer())->renderToString('zero-givers', $document);

        return PdfResponse::create($response, $pdf, ZeroGiversService::fileLabel());
    });
});
