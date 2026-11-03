<?php

use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\PledgeReminderService;
use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\Utils\RedirectUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->map(['GET', 'POST'], '/pledge-reminders/download', function (Request $request, Response $response): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $criteria = PledgeReminderService::criteriaFromInput($input);
        $_SESSION['idefaultFY'] = $criteria['fiscalYearId'];

        $document = PledgeReminderService::document($criteria);
        if ($document['letters'] === []) {
            RedirectUtils::redirect('FinancialReports.php?ReturnMessage=NoRows&ReportType=Pledge%20Reminders');
        }

        $pdf = (new MpdfRenderer())->renderToString('pledge-reminders', $document);

        return PdfResponse::create($response, $pdf, PledgeReminderService::fileLabel());
    });
});
