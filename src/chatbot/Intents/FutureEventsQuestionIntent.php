<?php
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\BotMan;
use ChurchCRM\EventQuery;


Class FutureEventsQuestionIntent extends EventsQuestionIntent{
    public function getSamples() {
        return array_merge(parent::getEventIntentSamples(),$this->getPresentTenseSamples());
        
    }
    public function getLabel() { 
        return "event-future";
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

    public function heard(IncomingMessage $message, $next, BotMan $bot) {
        // add records to the log
        $logger = LoggerUtils::getChatBotLogger();
        $logger->debug("Replying with future events");
        $now = new \DateTime();
        $later = new \DateTime();
        $later->add(new DateInterval('P7D'));
        $bot->replyInThread(parent::EventsToString($now,$later),[]);
        return $next($message);
    }

}