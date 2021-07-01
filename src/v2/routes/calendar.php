<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\CalendarQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;


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
