<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/envelopes', function (RouteCollectorProxy $group): void {
    $group->map(['GET', 'POST'], '', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Envelope Manager'),
            'sPageSubtitle' => gettext('Assign and manage donation envelope numbers'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Finance'), '/finance/'],
                [gettext('Manage Envelopes')],
            ]),
        ];
        // Full form logic still lives in the view for this first pass;
        // the legacy root file is removed and callers point here.
        return $renderer->render($response, 'envelopes/index.php', $pageArgs);
    });
});
