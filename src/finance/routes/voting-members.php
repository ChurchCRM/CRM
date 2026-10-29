<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\VotingMembersService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/reports', function (RouteCollectorProxy $group): void {
    $group->map(['GET', 'POST'], '/voting-members/download', function (Request $request, Response $response): Response {
        $input = $request->getMethod() === 'POST' ? (array) $request->getParsedBody() : $request->getQueryParams();
        $fiscalYearId = InputUtils::filterInt($input['FYID'] ?? '') ?: FiscalYearUtils::getCurrentFiscalYearId();
        $_SESSION['idefaultFY'] = $fiscalYearId;
        $requireDonationYears = max(0, InputUtils::filterInt($input['RequireDonationYears'] ?? ''));

        $report = VotingMembersService::report($fiscalYearId, $requireDonationYears);
        $pdf = (new MpdfRenderer())->renderToString('voting-members', $report + [
            'fiscal_year' => FinancialService::formatFiscalYear($fiscalYearId),
        ]);

        $filename = 'VotingMembers' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
        $response->getBody()->write($pdf);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', (SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    });
});
