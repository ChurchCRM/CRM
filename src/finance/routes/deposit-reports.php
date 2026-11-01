<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\Service\AdvancedDepositService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\Service\DepositReportService;
use ChurchCRM\Service\FinanceReportOptions;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $inputFor = static fn (Request $request): array => $request->getMethod() === 'POST'
        ? (array) $request->getParsedBody() : $request->getQueryParams();

    $page = static function (Response $response, array $input, string $error = '', ?array $summary = null): Response {
        $today = DateTimeUtils::getTodayDate();
        $input += ['DateStart' => substr($today, 0, 7) . '-01', 'DateEnd' => $today];
        $criteria = AdvancedDepositService::criteriaFromQuery($input);
        $deposits = FinanceReportOptions::deposits();
        if ($criteria['depositId'] > 0 && !in_array($criteria['depositId'], array_column($deposits, 'id'), true)) {
            $selected = DepositQuery::create()->findPk($criteria['depositId']);
            if ($selected !== null) {
                array_unshift($deposits, [
                    'id' => (int) $selected->getId(),
                    'label' => sprintf('%d — %s — %s', $selected->getId(), DateTimeUtils::formatDate($selected->getDate('Y-m-d')), $selected->getType()),
                ]);
            }
        }

        return (new PhpRenderer(__DIR__ . '/../views/'))->render($response->withHeader('Cache-Control', 'private, no-store'), 'reports/deposits.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Deposit Reports'),
            'sPageSubtitle' => gettext('Review payments for a period, or prepare one deposit for the bank.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'], [gettext('Deposit Reports')],
            ]),
            'criteria' => $criteria,
            'query' => $input,
            'error' => $error,
            'summary' => $summary,
            'deposits' => $deposits,
            'classifications' => FinanceReportOptions::classifications(),
            'families' => FinanceReportOptions::families(),
            'fundGroups' => FinanceReportOptions::fundGroups(),
        ]);
    };

    $group->map(['GET', 'POST'], '/deposits', function (Request $request, Response $response) use ($inputFor, $page): Response {
        $input = $inputFor($request);
        $error = AdvancedDepositService::validationError($input);
        $summary = null;
        if ($request->getMethod() === 'POST' && $error === '') {
            $records = AdvancedDepositService::records(AdvancedDepositService::criteriaFromQuery($input));
            $summary = AdvancedDepositService::summary($records);
            $error = $records === [] ? gettext('No records were returned. Adjust your filters and try again.') : '';
        }

        return $page($response, $input, $error, $summary);
    });

    $bankSlip = static function (Request $request, Response $response) use ($inputFor, $page): Response {
        $input = $inputFor($request);
        $id = DepositReportService::depositId($input);
        $report = DepositReportService::report($id);
        if ($report === null) {
            if ($request->getMethod() === 'HEAD') {
                return $response->withStatus(404);
            }
            if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return SlimUtils::renderErrorJSON($response, gettext('No Payments on this Deposit'), [], 404);
            }

            return $page($response, $input, $id > 0
                ? gettext('No Payments on this Deposit') : gettext('Select one deposit to print a bank slip.'));
        }
        $pdf = $request->getMethod() === 'HEAD' ? '' : (new MpdfRenderer())->renderToString('deposit', $report);

        return PdfResponse::create($response, $pdf, DepositReportService::fileLabel($id));
    };

    $download = static function (Request $request, Response $response) use ($inputFor, $page, $bankSlip): Response {
        $input = $inputFor($request);
        if (($input['mode'] ?? '') === 'slip') {
            return $bankSlip($request, $response);
        }
        $error = AdvancedDepositService::validationError($input);
        if ($error !== '') {
            return $page($response, $input, $error);
        }
        $criteria = AdvancedDepositService::criteriaFromQuery($input);
        $records = AdvancedDepositService::records($criteria);
        if ($records === []) {
            return $page($response, $input, gettext('No records were returned. Adjust your filters and try again.'));
        }
        if (($input['output'] ?? 'pdf') === 'csv') {
            [$headers, $rows] = AdvancedDepositService::csv($records);
            $exporter = new CsvExporter('UTF-8');
            $exporter->insertHeaders($headers)->insertRows($rows);
            $response->getBody()->write($exporter->getContent());

            return $response->withHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->withHeader('Content-Disposition', AdvancedDepositService::csvContentDisposition())
                ->withHeader('Cache-Control', 'private, no-store');
        }
        $pdf = (new MpdfRenderer())->renderToString('advanced-deposit', AdvancedDepositService::document($records, $criteria));

        return PdfResponse::create($response, $pdf, AdvancedDepositService::fileLabel());
    };

    $group->map(['GET', 'POST'], '/deposits/download', $download);
    $group->map(['GET', 'POST'], '/advanced-deposit/download', $download);
    $group->map(['GET', 'HEAD', 'POST'], '/print-deposit/download', $bankSlip);
});
