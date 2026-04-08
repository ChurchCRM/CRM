<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /event/cart-to-event — display the cart-to-event assignment page
$app->get('/cart-to-event', function (Request $request, Response $response) {
    AuthenticationManager::redirectHomeIfFalse(
        AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(),
        'ManageGroups'
    );

    $selectedEventType = 0;
    $params = $request->getQueryParams();
    if (isset($params['EventTypeFilter'])) {
        $selectedEventType = (int) $params['EventTypeFilter'];
    }

    $eventQuery = EventQuery::create();
    if ($selectedEventType > 0) {
        $eventType = EventTypeQuery::create()->findOneById($selectedEventType);
        if ($eventType) {
            $eventQuery->filterByEventType($eventType);
        }
    }

    $aPeopleInCart = [];
    $cartCount = 0;
    if (!empty($_SESSION['aPeopleCart'])) {
        $aPeopleInCart = PersonQuery::create()
            ->filterById($_SESSION['aPeopleCart'])
            ->find();
        $cartCount = count($aPeopleInCart);
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'cart-to-event.php', [
        'sRootPath'         => SystemURLs::getRootPath(),
        'sPageTitle'        => gettext('Add Cart to Event'),
        'sPageSubtitle'     => gettext('Assign cart items to an event'),
        'aBreadcrumbs'      => PageHeader::breadcrumbs([
            [gettext('Events'), '/ListEvents.php'],
            [gettext('Add Cart to Event')],
        ]),
        'aPeopleInCart'     => $aPeopleInCart,
        'cartCount'         => $cartCount,
        'aEvents'           => $eventQuery->find(),
        'aEventTypes'       => EventTypeQuery::create()->find(),
        'selectedEventType' => $selectedEventType,
    ]);
});

// POST /event/cart-to-event — process the form submission
$app->post('/cart-to-event', function (Request $request, Response $response) {
    AuthenticationManager::redirectHomeIfFalse(
        AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(),
        'ManageGroups'
    );

    $body = $request->getParsedBody();

    if (!empty($body['Submit']) && !empty($_SESSION['aPeopleCart']) && !empty($body['EventID'])) {
        $iEventID = (int) $body['EventID'];
        $event = EventQuery::create()->findPk($iEventID);
        $iCount = 0;

        if ($event !== null) {
            foreach ($_SESSION['aPeopleCart'] as $element) {
                try {
                    $event->checkInPerson((int) $element);
                    $iCount++;
                } catch (\Throwable $ex) {
                    $logger = LoggerUtils::getAppLogger();
                    $logger->error('An error occurred when saving event attendance', ['exception' => $ex]);
                }
            }
        }

        Cart::emptyAll();

        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/event/checkin/' . $iEventID . '?AddedCount=' . $iCount)
            ->withStatus(302);
    }

    // If form wasn't properly submitted, redirect back
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/event/cart-to-event')
        ->withStatus(302);
});
