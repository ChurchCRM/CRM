<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventCountName;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventType;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /event/types — list all event types
$app->get('/types', function (Request $request, Response $response) {
    if (!AuthenticationManager::getCurrentUser()->isAddEvent()) {
        return $response->withHeader('Location', SystemURLs::getRootPath())->withStatus(302);
    }

    $eventTypes = EventTypeQuery::create()->orderById()->find();
    $rows = [];

    foreach ($eventTypes as $et) {
        $startTime = $et->getDefStartTime();
        if ($startTime instanceof \DateTime) {
            $displayTime = $startTime->format('g:i A');
        } elseif (is_string($startTime) && $startTime !== '') {
            $dt = \DateTime::createFromFormat('H:i:s', $startTime);
            $displayTime = $dt ? $dt->format('g:i A') : $startTime;
        } else {
            $displayTime = '';
        }

        $recurType = $et->getDefRecurType();
        switch ($recurType) {
            case 'weekly':
                $recurText = gettext('Weekly on') . ' ' . gettext($et->getDefRecurDOW() . 's');
                break;
            case 'monthly':
                $recurText = gettext('Monthly on') . ' ' . date('jS', mktime(0, 0, 0, 1, (int) $et->getDefRecurDOM(), 2000));
                break;
            case 'yearly':
                $doy = $et->getDefRecurDOY('Y-m-d');
                $recurText = gettext('Yearly on') . ' ' . mb_substr((string) $doy, 5);
                break;
            default:
                $recurText = gettext('None');
        }

        // Count names
        $countNames = EventCountNameQuery::create()
            ->filterByTypeId((int) $et->getId())
            ->orderById()
            ->find();

        $countList = [];
        foreach ($countNames as $cn) {
            $countList[] = $cn->getName();
        }

        $rows[] = [
            'id'         => (int) $et->getId(),
            'name'       => $et->getName(),
            'recurText'  => $recurText,
            'startTime'  => $displayTime,
            'countList'  => implode(', ', $countList),
            'active'     => (int) $et->getActive(),
        ];
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'types-list.php', [
        'sRootPath'      => SystemURLs::getRootPath(),
        'sPageTitle'     => gettext('Event Types'),
        'sPageSubtitle'  => gettext('Manage event names, recurrence patterns, and attendance counts'),
        'aBreadcrumbs'   => PageHeader::breadcrumbs([
            [gettext('Events'), '/event/dashboard'],
            [gettext('Event Types')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Add Event Type'), 'url' => '/event/types/new', 'icon' => 'fa-plus', 'adminOnly' => false],
        ]),
        'rows' => $rows,
    ]);
});

// GET /event/types/new — create new event type form
$app->get('/types/new', function (Request $request, Response $response) {
    if (!AuthenticationManager::getCurrentUser()->isAddEvent()) {
        return $response->withHeader('Location', SystemURLs::getRootPath())->withStatus(302);
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'types-new.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('Add New') . ' ' . gettext('Event Type'),
        'sPageSubtitle' => gettext('Create a new event type with recurrence and attendance settings'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Events'), '/event/dashboard'],
            [gettext('Event Types'), '/event/types'],
            [gettext('New')],
        ]),
    ]);
});

// POST /event/types/new — create new event type
$app->post('/types/new', function (Request $request, Response $response) {
    if (!AuthenticationManager::getCurrentUser()->isAddEvent()) {
        return $response->withHeader('Location', SystemURLs::getRootPath())->withStatus(302);
    }

    $body = $request->getParsedBody();

    $eName = InputUtils::legacyFilterInput($body['newEvtName'] ?? '');
    if (empty($eName)) {
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/types/new')->withStatus(302);
    }

    $eTime = $body['newEvtStartTime'] ?? '';
    if (!empty($eTime)) {
        $dt = \DateTime::createFromFormat('g:i A', $eTime);
        if ($dt) {
            $eTime = $dt->format('H:i:s');
        }
    }

    $eRecur = InputUtils::legacyFilterInput($body['newEvtTypeRecur'] ?? '');
    $eDOW = InputUtils::legacyFilterInput($body['newEvtRecurDOW'] ?? '');
    $eDOM = InputUtils::legacyFilterInput($body['newEvtRecurDOM'] ?? '');
    $eDOY = InputUtils::legacyFilterInput($body['newEvtRecurDOY'] ?? '');
    $eCntLst = $body['newEvtTypeCntLst'] ?? '';
    $eCntArray = array_filter(array_map('trim', explode(',', $eCntLst)));

    $eventType = new EventType();
    $eventType->setName($eName);
    if (!empty($eTime)) {
        $eventType->setDefStartTime($eTime);
    }
    if (!empty($eRecur)) {
        $eventType->setDefRecurType($eRecur);
    }
    if (!empty($eDOW)) {
        $dayOfWeekMap = ['1' => 'Sunday', '2' => 'Monday', '3' => 'Tuesday', '4' => 'Wednesday', '5' => 'Thursday', '6' => 'Friday', '7' => 'Saturday'];
        if (isset($dayOfWeekMap[$eDOW])) {
            $eDOW = $dayOfWeekMap[$eDOW];
        }
        $eventType->setDefRecurDOW($eDOW);
    }
    if (!empty($eDOM)) {
        $eventType->setDefRecurDOM($eDOM);
    }
    if (!empty($eDOY)) {
        $eventType->setDefRecurDOY($eDOY);
    }
    $eventType->setActive(1);
    $eventType->save();

    $newId = (int) $eventType->getId();

    foreach ($eCntArray as $cCnt) {
        $existing = EventCountNameQuery::create()
            ->filterByTypeId($newId)
            ->filterByName($cCnt)
            ->findOne();
        if ($existing === null) {
            $countName = new EventCountName();
            $countName->setTypeId($newId);
            $countName->setName($cCnt);
            $countName->save();
        }
    }

    return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/types')->withStatus(302);
});

// GET /event/types/{id} — edit event type
$app->get('/types/{id}', function (Request $request, Response $response, array $args) {
    if (!AuthenticationManager::getCurrentUser()->isAddEvent()) {
        return $response->withHeader('Location', SystemURLs::getRootPath())->withStatus(302);
    }

    $tyid = (int) $args['id'];
    $eventType = EventTypeQuery::create()->findOneById($tyid);

    if ($eventType === null) {
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/types')->withStatus(302);
    }

    $defStartTime = $eventType->getDefStartTime();
    if ($defStartTime instanceof \DateTime) {
        $startTimeDisplay = $defStartTime->format('g:i A');
    } elseif (is_string($defStartTime) && $defStartTime !== '') {
        $dt = \DateTime::createFromFormat('H:i:s', $defStartTime);
        $startTimeDisplay = $dt ? $dt->format('g:i A') : '9:00 AM';
    } else {
        $startTimeDisplay = '9:00 AM';
    }

    $recurType = $eventType->getDefRecurType();
    switch ($recurType) {
        case 'weekly':
            $recurText = gettext('Weekly on') . ' ' . gettext($eventType->getDefRecurDOW() . 's');
            break;
        case 'monthly':
            $recurText = gettext('Monthly on') . ' ' . date('jS', mktime(0, 0, 0, 1, (int) $eventType->getDefRecurDOM(), 2000));
            break;
        case 'yearly':
            $doy = $eventType->getDefRecurDOY('Y-m-d');
            $recurText = gettext('Yearly on') . ' ' . mb_substr((string) $doy, 5);
            break;
        default:
            $recurText = gettext('None');
    }

    $counts = EventCountNameQuery::create()->filterByTypeId($tyid)->orderById()->find();
    $countsArray = [];
    foreach ($counts as $c) {
        $countsArray[] = ['id' => (int) $c->getId(), 'name' => $c->getName()];
    }

    $groups = GroupQuery::create()->orderByName()->find();

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'types-edit.php', [
        'sRootPath'        => SystemURLs::getRootPath(),
        'sPageTitle'       => gettext('Edit Event Type'),
        'sPageSubtitle'    => $eventType->getName(),
        'aBreadcrumbs'     => PageHeader::breadcrumbs([
            [gettext('Events'), '/event/dashboard'],
            [gettext('Event Types'), '/event/types'],
            [gettext('Edit')],
        ]),
        'eventType'        => $eventType,
        'startTimeDisplay' => $startTimeDisplay,
        'recurText'        => $recurText,
        'counts'           => $countsArray,
        'groups'           => $groups,
    ]);
});

// POST /event/types/{id} — handle edit actions
$app->post('/types/{id}', function (Request $request, Response $response, array $args) {
    if (!AuthenticationManager::getCurrentUser()->isAddEvent()) {
        return $response->withHeader('Location', SystemURLs::getRootPath())->withStatus(302);
    }

    $tyid = (int) $args['id'];
    $body = $request->getParsedBody();
    $action = $body['Action'] ?? '';

    // Handle DELETE_<countId> action for removing a count
    if (strpos($action, 'DELETE_') === 0) {
        $ctid = (int) mb_substr($action, 7);
        EventCountNameQuery::create()->filterById($ctid)->delete();
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/types/' . $tyid)->withStatus(302);
    }

    $eventType = EventTypeQuery::create()->findOneById($tyid);
    if ($eventType === null) {
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/types')->withStatus(302);
    }

    switch ($action) {
        case 'ADD':
            $newCTName = InputUtils::legacyFilterInput($body['newCountName'] ?? '');
            if (!empty($newCTName)) {
                $eventCount = new EventCountName();
                $eventCount->setTypeId($tyid);
                $eventCount->setName($newCTName);
                $eventCount->save();
            }
            break;

        case 'NAME':
            $eName = InputUtils::legacyFilterInput($body['newEvtName'] ?? '');
            if (!empty($eName)) {
                $eventType->setName($eName);
                $eventType->save();
            }
            break;

        case 'TIME':
            $eTime = $body['newEvtStartTime'] ?? '';
            $dt = \DateTime::createFromFormat('g:i A', $eTime);
            if ($dt) {
                $eventType->setDefStartTime($dt->format('H:i:s'));
                $eventType->save();
            }
            break;

        case 'SAVE':
            $active = isset($body['type_active']) ? 1 : 0;
            $groupId = (int) ($body['type_grpid'] ?? 0);
            $eventType->setActive($active);
            $eventType->setGroupId($groupId);
            $eventType->save();
            break;
    }

    return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/types/' . $tyid)->withStatus(302);
});

// POST /event/types/{id}/delete — delete event type
$app->post('/types/{id}/delete', function (Request $request, Response $response, array $args) {
    if (!AuthenticationManager::getCurrentUser()->isAddEvent()) {
        return $response->withHeader('Location', SystemURLs::getRootPath())->withStatus(302);
    }

    $tyid = (int) $args['id'];
    EventTypeQuery::create()->filterById($tyid)->delete();
    EventCountNameQuery::create()->filterByTypeId($tyid)->delete();

    return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/types')->withStatus(302);
});
