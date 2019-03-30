<?php
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\BotMan;
use ChurchCRM\EventQuery;


Class PastEventsQuestionIntent extends EventsQuestionIntent{
    public function getSamples() {
        return array_merge(parent::getSamples(),$this->getPastTenseSamples());
        
    }
    public function getLabel() { 
        return "event-past";
    }

    public function getResponse() {
        return "Eventually";
    }

    protected function getPastTenseSamples() {
        return [ 'past',
        'yesterday',
        'earlier',
        'last week'];

    }

    public function heard(IncomingMessage $message, $next, BotMan $bot) {
        // add records to the log
        $logger = LoggerUtils::getChatBotLogger();
        $logger->debug("Replying with past events");
        $now = new \DateTime();
        $earlier = new \DateTime();
        $di = new DateInterval('P7D');
        $di->invert = 1;
        $earlier->add($di);
        $bot->replyInThread(parent::EventsToString($earlier,$now),[]);
        return $next($message);
    }

}