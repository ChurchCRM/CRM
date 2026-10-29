<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\PledgeReminderService;
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
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/FinancialReports.php?ReturnMessage=NoRows&ReportType=' . rawurlencode('Pledge Reminders'))
                ->withStatus(302);
        }

        $pdf = (new MpdfRenderer())->renderToString('pledge-reminders', $document);

        $filename = 'ReminderReport' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Length', (string) strlen($pdf))
            ->withHeader('Content-Disposition', (SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    });
});
