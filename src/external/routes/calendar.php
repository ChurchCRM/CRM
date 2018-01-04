<?php

// Routes

use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$app->group('/calendar', function () {
    $this->get('/events', function ($request, $response, $args) {
        if (!ChurchCRM\dto\SystemConfig::getBooleanValue("bEnableExternalCalendarAPI"))
        {
          throw new \Exception(gettext("External Calendar API is disabled")  , 400);
        }

        $params = $request->getQueryParams();
        $start_date = DateTime::createFromFormat("Y-m-d",$params['start']);
        $start_date->setTime(0,0,0);
        $max_events = InputUtils::FilterInt($params['max']);

        $events = ChurchCRM\EventQuery::create()
                ->filterByPubliclyVisible(true)
                ->orderByStart(Criteria::ASC);

        if($start_date) {
          $events->filterByStart($start_date,  Criteria::GREATER_EQUAL);
        }

        if ($max_events) {
          $events->limit($max_events);
        }
        
        return $response->withJson($events->find()->toArray());

    });
});
