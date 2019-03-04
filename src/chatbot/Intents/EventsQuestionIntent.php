<?php
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\BotMan;
use ChurchCRM\EventQuery;


Class EventsQuestionIntent extends ChatbotIntent{
    public function getSamples() {
        return array_merge([
            'time',
            'when',
            'events',
            'upcoming',
            'calendar',
            "happening"
        ],$this->getPastTenseSamples(),$this->getPresentTenseSamples());
        
    }
    public function getLabel() { 
        return "event";
    }

    public function getResponse() {
        return "Eventually";
    }

    protected function getPresentTenseSamples() {
        return [ 'this', 
        'today',
        'tomorrow',
        'future',
        'next'];
    }


    protected function getPastTenseSamples() {
        return [ 'past',
        'yesterday',
        'earlier',
        'last week'];
        
        /* $earlier = new \DateTime();
            $di = new DateInterval('P7D');
            $di->invert = 1;
            $earlier->add($di);.*/
    }

    public function heard(IncomingMessage $message, $next, BotMan $bot) {
        // add records to the log
        $logger = LoggerUtils::getChatBotLogger();
        $logger->info("Replying with events");
        $bot->replyInThread($this->EventsToString(),[]);
        return $next($message);
    }

    private function EventsToString() {
        $logger = LoggerUtils::getChatBotLogger();
        try {
            
            /** @var ChurchCRM\EventQuery $events */
            $now = new \DateTime();
            $later = new \DateTime();
            $later->add(new DateInterval('P7D'));
            $logger->info("looking for events between " . $now->format('Y-m-d H:i:s') . " and " . $later->format('Y-m-d H:i:s'));
            $events  = EventQuery::Create() 
                -> orderByStart() 
                ->filterByStart(array("min" => $now))
                ->filterByEnd(array("max" => $later))
                ->find();

            $strings = [];
            $strings[0]  = "Found " . count($events) . " events between " . $now->format('Y-m-d H:i:s') . " and " . $later->format('Y-m-d H:i:s');
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