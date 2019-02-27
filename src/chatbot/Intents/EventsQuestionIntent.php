<?php
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\BotMan;
use ChurchCRM\EventQuery;


Class EventsQuestionIntent implements ChatbotIntent{
    public function getSamples() {
        return [
            'time',
            'when',
            'upcoming events',
            'calendar',
            "happening this week"
        ];
        
    }
    public function getLabel() { 
        return "event";
    }

    public function getResponse() {
        return "Eventually";
    }

    public function matching(IncomingMessage $message, $pattern, $regexMatched) {
        $logger = LoggerUtils::getChatBotLogger();
        $logger->info(print_r($message->getExtras(),true));
        $matched = $message->getExtras('MatchedIntent') instanceof self;
        $logger->info("This is" . ($matched ? "":" not") . " " . $this->getLabel());
        return $matched;
    }

    public function heard(IncomingMessage $message, $next, BotMan $bot) {
        // add records to the log
        $logger = LoggerUtils::getChatBotLogger();
        $logger->info("Replying with an event");
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

            $strings  = ["Found " . count($events) . " events:"];
            $i = 0;
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