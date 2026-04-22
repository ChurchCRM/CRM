<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * GET /event/editor[/{id}]
 *
 * Thin shell — renders only the PageHeader + a mount point. The actual
 * form is rendered client-side by webpack/event-editor.js, which boots
 * the shared renderer in webpack/event-form.js (the same renderer
 * powers the calendar modal). The POST handler that used to live here
 * has been removed: the form saves directly to POST /api/events (new)
 * or POST /api/events/:id (update).
 */
$app->get('/editor[/{id}]', function (Request $request, Response $response, array $args) {
    $params = $request->getQueryParams();
    $eventId = (int) ($args['id'] ?? 0);
    $typeId = (int) ($params['typeId'] ?? 0);

    $eventExists = false;
    $sEventTitle = '';
    $iTypeID = $typeId;

    if ($eventId > 0) {
        $event = EventQuery::create()->findOneById($eventId);
        if ($event === null) {
            LoggerUtils::getAppLogger()->warning('Event not found: ' . $eventId);

            return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/dashboard')->withStatus(302);
        }
        $eventExists = true;
        $sEventTitle = (string) $event->getTitle();
        $iTypeID = (int) $event->getType();
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'editor.php', [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => $eventExists ? gettext('Edit Event') : gettext('Create Event'),
        'sPageSubtitle' => $eventExists && $sEventTitle !== '' ? $sEventTitle : gettext('Create and manage church events and activities'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Events'), '/event/dashboard'],
            [$eventExists ? gettext('Edit Event') : gettext('Create Event')],
        ]),
        'eventId'      => $eventId,
        'eventExists'  => $eventExists,
        'iTypeID'      => $iTypeID,
    ]);
})->add(new AddEventsRoleAuthMiddleware());
