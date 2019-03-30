<?php
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\BotMan;
use ChurchCRM\EventQuery;


Class EventsQuestionIntent extends ChatbotIntent{
    public function getSamples() {
        return [
            'time',
            'when',
            'events',
            'upcoming',
            'calendar',
            "happening"
        ];
        
    }
    public function getLabel() { 
        return "event";
    }

    public function getResponse() {
        return "Eventually";
    }  

    public function heard(IncomingMessage $message, $next, BotMan $bot) {
        // add records to the log
        $logger = LoggerUtils::getChatBotLogger();
        $logger->debug("Replying with events");
        $bot->replyInThread($this->EventsToString(),[]);
        return $next($message);
    }

    protected function EventsToString(\DateTime $StartDate, \DateTime $EndDate) {
        $logger = LoggerUtils::getChatBotLogger();
        try {
            
            /** @var ChurchCRM\EventQuery $events */
         
            $logger->info("looking for events between " . $StartDate->format('Y-m-d H:i:s') . " and " . $EndDate->format('Y-m-d H:i:s'));
            $events  = EventQuery::Create() 
                -> orderByStart() 
                ->filterByStart(array("min" => $StartDate))
                ->filterByEnd(array("max" => $EndDate))
                ->find();

            $strings = [];
            $strings[0]  = "Found " . count($events) . " events between " . $StartDate->format('Y-m-d H:i:s') . " and " . $EndDate->format('Y-m-d H:i:s');
            $i = 1;
            foreach($events as $event)
            {
                /** @var ChurchCRM\Event $event */
                $strings[$i] = "*" . $event->getTitle() . "*\n";
                $strings[$i] .= "(" . implode(";",$event->getPinnedCalendarNames()). ")\n";
                $strings[$i] .= "_" . $event->getStart("m/d/Y") . "_";
                $i ++;
            }
            return implode("\n\n",$strings);
        }
        catch (Exception $e)
        {
            $logger->warn("Could not find for events: " . $e);
            return "Error looking up events";
        }
    }
}