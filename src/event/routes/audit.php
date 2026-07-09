<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /event/audit — show the stuck-event audit dashboard
$app->get('/audit', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'audit.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Event Audit'),
        'sPageSubtitle' => gettext('Find past events that are still active or have un-checked-out attendees'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Events'), '/event/dashboard'],
            [gettext('Audit')],
        ]),
    ]);
})->add(new AddEventsRoleAuthMiddleware());
