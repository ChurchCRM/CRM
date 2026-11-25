<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FinanceReportOptions;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\PledgeReminderService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports/pledge-reminders', function (RouteCollectorProxy $group): void {
    $page = static function (Response $response, array $criteria, string $message = ''): Response {
        return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'reports/pledge-reminders.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Pledge Reminders'),
            'sPageSubtitle' => gettext('Generate reminder letters for families with outstanding pledges.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Financial Reports'), '/finance/reports'],
                [gettext('Pledge Reminders')],
            ]),
            'criteria' => $criteria,
            'message' => $message,
            'fiscalYears' => FiscalYearUtils::buildFiscalYearList(min((new FinancialService())->getOldestPledgeFyId(), $criteria['fiscalYearId'])),
            'classifications' => FinanceReportOptions::classifications(),
            'families' => FinanceReportOptions::families(),
            'fundGroups' => FinanceReportOptions::fundGroups(),
        ]);
    };

    $group->get('', function (Request $request, Response $response) use ($page): Response {
        return $page($response, PledgeReminderService::criteriaFromInput(
            $request->getQueryParams() + ['FYID' => $_SESSION['idefaultFY'] ?? '']
        ));
    });

    $group->map(['GET', 'POST'], '/download', function (Request $request, Response $response) use ($page): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $criteria = PledgeReminderService::criteriaFromInput($input);
        $_SESSION['idefaultFY'] = $criteria['fiscalYearId'];

        $document = PledgeReminderService::document($criteria);
        if ($document['letters'] === []) {
            return $page($response, $criteria, sprintf(
                gettext('No family matches these filters for %s.'),
                FiscalYearUtils::formatFiscalYearLabel($criteria['fiscalYearId'])
            ));
        }

        $pdf = (new MpdfRenderer())->renderToString('pledge-reminders', $document);

        return PdfResponse::create($response, $pdf, PledgeReminderService::fileLabel());
    });
});
