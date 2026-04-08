<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Service\EventService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /event/repeat-editor — display the repeat event creation form
$app->get('/repeat-editor[/{typeId}]', function (Request $request, Response $response, array $args) {
    $params = $request->getQueryParams();
    $typeId = (int) ($args['typeId'] ?? $params['EN_tyid'] ?? 0);

    $eventType = null;
    $typeName = '';
    $defStartTime = '09:00';
    $defEndTime = '10:00';
    $defRecurType = 'weekly';
    $defRecurDOW = 'Sunday';
    $defRecurDOM = 1;
    $defRecurDOY = '01-01';

    if ($typeId > 0) {
        $eventType = EventTypeQuery::create()->findOneById($typeId);
        if ($eventType !== null) {
            $typeName = $eventType->getName();
            $defStart = $eventType->getDefStartTime();
            if ($defStart instanceof \DateTime) {
                $defStartTime = $defStart->format('H:i');
                $endHour = ((int) $defStart->format('H') + 1) % 24;
                $defEndTime = sprintf('%02d:%s', $endHour, $defStart->format('i'));
            } elseif (!empty($defStart)) {
                $dt = \DateTime::createFromFormat('H:i:s', (string) $defStart) ?: \DateTime::createFromFormat('H:i', (string) $defStart);
                if ($dt) {
                    $defStartTime = $dt->format('H:i');
                    $endHour = ((int) $dt->format('H') + 1) % 24;
                    $defEndTime = sprintf('%02d:%s', $endHour, $dt->format('i'));
                }
            }
            $defRecurType = $eventType->getDefRecurType() ?: 'weekly';
            $defRecurDOW = $eventType->getDefRecurDow() ?: 'Sunday';
            $rawDOM = $eventType->getDefRecurDom();
            $defRecurDOM = ($rawDOM !== null && $rawDOM !== '') ? (int) $rawDOM : 1;
            $defDOY = $eventType->getDefRecurDoy();
            if ($defDOY instanceof \DateTime) {
                $defRecurDOY = $defDOY->format('m-d');
            } elseif (!empty($defDOY)) {
                $parts = explode('-', (string) $defDOY);
                if (count($parts) >= 3) {
                    $defRecurDOY = $parts[1] . '-' . $parts[2];
                } elseif (count($parts) === 2) {
                    $defRecurDOY = (string) $defDOY;
                }
            }
        }
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'repeat-editor.php', [
        'sRootPath'       => SystemURLs::getRootPath(),
        'sPageTitle'      => gettext('Create Repeat Events'),
        'sPageSubtitle'   => gettext('Bulk-create a series of recurring events'),
        'aBreadcrumbs'    => PageHeader::breadcrumbs([
            [gettext('Events'), '/ListEvents.php'],
            [gettext('Create Repeat Events')],
        ]),
        'typeId'          => $typeId,
        'eventType'       => $eventType,
        'typeName'        => $typeName,
        'defStartTime'    => $defStartTime,
        'defEndTime'      => $defEndTime,
        'defRecurType'    => $defRecurType,
        'defRecurDOW'     => $defRecurDOW,
        'defRecurDOM'     => $defRecurDOM,
        'defRecurDOY'     => $defRecurDOY,
        'allEventTypes'   => EventTypeQuery::create()->orderByName()->find(),
        'allCalendars'    => CalendarQuery::create()->orderByName()->find(),
        'allGroups'       => GroupQuery::create()->orderByName()->find(),
        'rangeStart'      => DateTimeUtils::getTodayDate(),
        'rangeEnd'        => (new \DateTime('+1 year'))->format('Y-m-d'),
    ]);
});

// POST /event/repeat-editor — process repeat event creation
$app->post('/repeat-editor', function (Request $request, Response $response) {
    $body = $request->getParsedBody();

    $iTypeID      = InputUtils::filterInt($body['EventTypeID'] ?? 0);
    $sTitle       = InputUtils::legacyFilterInput($body['EventTitle'] ?? '');
    $sDesc        = InputUtils::sanitizeHTML($body['EventDescInput'] ?? '');
    $sStartTime   = InputUtils::legacyFilterInput($body['StartTime'] ?? '09:00');
    $sEndTime     = InputUtils::legacyFilterInput($body['EndTime'] ?? '10:00');
    $sRecurType   = InputUtils::legacyFilterInput($body['RecurType'] ?? '');
    $sRecurDOW    = InputUtils::legacyFilterInput($body['RecurDOW'] ?? 'Sunday');
    $iRecurDOM    = InputUtils::filterInt($body['RecurDOM'] ?? 1);
    $sRecurDOY    = InputUtils::legacyFilterInput($body['RecurDOY'] ?? '01-01');
    $sRangeStart  = InputUtils::legacyFilterInput($body['RangeStart'] ?? '');
    $sRangeEnd    = InputUtils::legacyFilterInput($body['RangeEnd'] ?? '');
    $iLinkedGroup = InputUtils::filterInt($body['LinkedGroupId'] ?? 0);
    $pinnedCalendars = [];
    if (!empty($body['PinnedCalendars'])) {
        foreach ((array) $body['PinnedCalendars'] as $calId) {
            $pinnedCalendars[] = (int) $calId;
        }
    }

    $validRecurTypes = ['weekly', 'monthly', 'yearly'];
    $errorMsg = '';

    if (empty($iTypeID)) {
        $errorMsg = gettext('You must select an event type.');
    } elseif (empty($sTitle)) {
        $errorMsg = gettext('Event title is required.');
    } elseif (!in_array($sRecurType, $validRecurTypes, true)) {
        $errorMsg = gettext('You must select a valid recurrence pattern.');
    } elseif (empty($sRangeStart) || empty($sRangeEnd)) {
        $errorMsg = gettext('You must specify a date range.');
    } elseif ($sRangeStart > $sRangeEnd) {
        $errorMsg = gettext('Range start must be before range end.');
    }

    if (!empty($errorMsg)) {
        $_SESSION['sGlobalMessage'] = $errorMsg;
        $_SESSION['sGlobalMessageClass'] = 'danger';
        $target = SystemURLs::getRootPath() . '/event/repeat-editor';
        if ($iTypeID > 0) {
            $target .= '/' . $iTypeID;
        }

        return $response->withHeader('Location', $target)->withStatus(302);
    }

    try {
        $service = new EventService();
        $createdIds = $service->createRepeatEvents([
            'title'           => $sTitle,
            'typeId'          => $iTypeID,
            'desc'            => $sDesc,
            'text'            => '',
            'startTime'       => $sStartTime,
            'endTime'         => $sEndTime,
            'recurType'       => $sRecurType,
            'recurDOW'        => $sRecurDOW,
            'recurDOM'        => $iRecurDOM,
            'recurDOY'        => $sRecurDOY,
            'rangeStart'      => $sRangeStart,
            'rangeEnd'        => $sRangeEnd,
            'pinnedCalendars' => $pinnedCalendars,
            'linkedGroupId'   => $iLinkedGroup,
            'inactive'        => InputUtils::filterInt($body['EventStatus'] ?? 0),
        ]);
        $count = count($createdIds);
        $_SESSION['sGlobalMessage'] = sprintf(
            ngettext('%d repeat event created successfully.', '%d repeat events created successfully.', $count),
            $count
        );
        $_SESSION['sGlobalMessageClass'] = 'success';
    } catch (\InvalidArgumentException $e) {
        $_SESSION['sGlobalMessage'] = $e->getMessage();
        $_SESSION['sGlobalMessageClass'] = 'danger';
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/ListEvents.php')
        ->withStatus(302);
});
