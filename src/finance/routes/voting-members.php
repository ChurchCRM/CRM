<?php

use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\VotingMembersService;
use ChurchCRM\view\PageHeader;
use ChurchCRM\Utils\PdfFilename;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/reports/voting-members', function (RouteCollectorProxy $group): void {
    $page = static function (Response $response, array $input, string $error = ''): Response {
        return (new PhpRenderer(__DIR__ . '/../views/'))->render($response->withHeader('Cache-Control', 'private, no-store'), 'reports/voting-members.php', [
            'sPageTitle' => gettext('Voting Members'),
            'sPageSubtitle' => gettext('Prepare a list for church review using your bylaws.'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([[gettext('Financial Reports'), '/finance/reports'], [gettext('Voting Members')]]),
            'query' => $input + VotingMembersService::defaults(), 'classifications' => Classification::getAll(), 'error' => $error,
        ]);
    };
    $group->get('', function (Request $request, Response $response) use ($page): Response {
        return $page($response, $request->getQueryParams());
    });
    $group->map(['GET', 'POST'], '/download', function (Request $request, Response $response) use ($page): Response {
        $input = SlimUtils::requestInput($request);
        // A blank fiscal year uses the current fiscal year.
        if (($input['FYID'] ?? null) === '') {
            unset($input['FYID']);
        }
        try {
            $criteria = VotingMembersService::criteria($input);
        } catch (InvalidArgumentException $exception) {
            return $page($response->withStatus(422), $input, $exception->getMessage());
        }
        $report = VotingMembersService::report($criteria);
        if ($report['people'] === []) {
            return $page($response, $input, gettext('No people match these filters. Check missing birth or membership dates and adjust your criteria.'));
        }
        $pdf = (new MpdfRenderer())->renderToString('voting-members', $report);
        $fileLabel = PdfFilename::dated('VotingMembers');
        return PdfResponse::create($response, $pdf, $fileLabel);
    });
})->add(function (Request $request, $handler): Response {
    if ($request->getHeaderLine('x-api-key') !== '') {
        return (new \Laminas\Diactoros\Response())->withStatus(403)->withHeader('Cache-Control', 'private, no-store');
    }
    if (!AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
        return (new \Laminas\Diactoros\Response())->withStatus(302)
            ->withHeader('Location', SystemURLs::getRootPath() . '/v2/access-denied?role=Finance');
    }

    return $handler->handle($request);
});
