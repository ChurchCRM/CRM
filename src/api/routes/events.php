<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Base\EventQuery;
use ChurchCRM\Event;
use ChurchCRM\EventCountsQuery;
use ChurchCRM\EventCounts;
use ChurchCRM\Service\CalendarService;


$app->group('/events', function () {

    $this->get('/', function ($request, $response, $args) {
        $Events= EventQuery::create()
                ->find();
        return $response->write($Events->toJSON());
    });
   
    $this->get('/notDone', function ($request, $response, $args) {
         $Events= EventQuery::create()
                 ->filterByEnd(new DateTime(),  Propel\Runtime\ActiveQuery\Criteria::GREATER_EQUAL)
                ->find();
        return $response->write($Events->toJSON());
    });
    
     
    $this->post('/', function ($request, $response, $args) {
      $input = (object) $request->getParsedBody();
      
       if (!strcmp($input->evntAction,'createEvent'))
       {
           $event = new Event; 
           $event->setTitle($input->EventTitle);
           $event->setType($input->eventTypeID);
           $event->setDesc($input->EventDesc);
           $event->setGroupId($input->EventGroupID);  
           $event->setStart(str_replace("T"," ",$input->start));
           $event->setEnd(str_replace("T"," ",$input->end));
           $event->setText($input->eventPredication);
           $event->save(); 
       
           if ($input->Total > 0 || $input->Visitors || $input->Members){
             $eventCount = new EventCounts; 
             $eventCount->setEvtcntEventid($event->getID());
             $eventCount->setEvtcntCountid(1);
             $eventCount->setEvtcntCountname('Total');
             $eventCount->setEvtcntCountcount($input->Total);
             $eventCount->setEvtcntNotes($input->EventCountNotes);
             $eventCount->save();

             $eventCount = new EventCounts; 
             $eventCount->setEvtcntEventid($event->getID());
             $eventCount->setEvtcntCountid(2);
             $eventCount->setEvtcntCountname('Members');
             $eventCount->setEvtcntCountcount($input->Members);
             $eventCount->setEvtcntNotes($input->EventCountNotes);
             $eventCount->save();

             $eventCount = new EventCounts; 
             $eventCount->setEvtcntEventid($event->getID());
             $eventCount->setEvtcntCountid(3);
             $eventCount->setEvtcntCountname('Visitors');
             $eventCount->setEvtcntCountcount($input->Visitors);
             $eventCount->setEvtcntNotes($input->EventCountNotes);
             $eventCount->save();
           }
       
           $calendarS = new CalendarService();
           $realCalEvnt = $calendarS->createCalendarItem('event',
              $event->getTitle(), $event->getStart('Y-m-d H:i:s'), $event->getEnd('Y-m-d H:i:s'), $event->getEventURI(),$event->getId(),$event->getGroupId());// only the event id sould be edited and moved and have custom color
        
           return $response->withJson(array_filter($realCalEvnt));
       
       } 
       else if (!strcmp($input->evntAction,'moveEvent'))
       {
          $event = EventQuery::Create()
            ->findOneById($input->eventID);
     
     
         $oldStart = new DateTime($event->getStart('Y-m-d H:i:s'));     
         $oldEnd = new DateTime($event->getEnd('Y-m-d H:i:s'));

         $newStart = new DateTime(str_replace("T"," ",$input->start));     
     
         if ($newStart < $oldStart)
         {
          $interval = $oldStart->diff($newStart);
          $newEnd = $oldEnd->add($interval);          
         }
         else 
         {
          $interval = $newStart->diff($oldStart);
          $newEnd = $oldEnd->sub($interval);          
         }

         $event->setStart($newStart->format('Y-m-d H:i:s'));
         $event->setEnd($newEnd->format('Y-m-d H:i:s'));
         $event->save();
    
          $calendarS = new CalendarService();
          $realCalEvnt = $calendarS->createCalendarItem('event',
            $event->getTitle(), $event->getStart('Y-m-d H:i:s'), $event->getEnd('Y-m-d H:i:s'), $event->getEventURI(),$event->getId(),$event->getGroupId());// only the event id sould be edited and moved and have custom color
    
          return $response->withJson(array_filter($realCalEvnt));
       }
       else if (!strcmp($input->evntAction,'retriveEvent'))
       { 
          $event = EventQuery::Create()
            ->findOneById($input->eventID);
      
          $calendarS = new CalendarService();
          $realCalEvnt = $calendarS->createCalendarItem('event',
              $event->getTitle(), $event->getStart('Y-m-d H:i:s'), $event->getEnd('Y-m-d H:i:s'), $event->getEventURI(),$event->getId(),$event->getGroupId());// only the event id sould be edited and moved and have custom color
    
          return $response->withJson(array_filter($realCalEvnt));
      }
      else if (!strcmp($input->evntAction,'resizeEvent'))
       {
          $event = EventQuery::Create()
            ->findOneById($input->eventID);
          
         $event->setEnd(str_replace("T"," ",$input->end));
         $event->save();
    
          $calendarS = new CalendarService();
          $realCalEvnt = $calendarS->createCalendarItem('event',
            $event->getTitle(), $event->getStart('Y-m-d H:i:s'), $event->getEnd('Y-m-d H:i:s'), $event->getEventURI(),$event->getId(),$event->getGroupId());// only the event id sould be edited and moved and have custom color
    
          return $response->withJson(array_filter($realCalEvnt));
       }
       

       //return $response->write($event->toJSON());
    });
});
