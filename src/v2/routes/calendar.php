<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\CalendarQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Authentication\AuthenticationManager;


$app->group('/calendar', function () {
    $this->get('/', 'getCalendar');
    $this->get('', 'getCalendar');
});

function getCalendar(Request $request, Response $response, array $args) {
    $renderer = new PhpRenderer('templates/calendar/');

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Calendar'),
        'calendarJSArgs' => getCalendarJSArgs()
    ];

    return $renderer->render($response, 'calendar.php', $pageArgs);
}

function getCalendarJSArgs() {
  return array(
      'isModifiable' => AuthenticationManager::GetCurrentUser()->isAddEvent(),
      'countCalendarAccessTokens' => CalendarQuery::create()->filterByAccessToken(null, Criteria::NOT_EQUAL)->count(),
      'bEnableExternalCalendarAPI' => SystemConfig::getBooleanValue("bEnableExternalCalendarAPI")
  );
}
