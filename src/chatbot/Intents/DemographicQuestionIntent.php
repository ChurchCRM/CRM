<?php

use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\BotMan;

Class DemographicQuestionIntent implements ChatbotIntent {
    public function getSamples() {
        return [
            'phone number',
            'email address',
            'phone number',
            "who"
        ];
        
    }
    public function getLabel() { 
        return "demographic";
    }

    public function getResponse() {
        return "Someone";
    }

    public function matching(IncomingMessage $message, $pattern, $regexMatched) {
        $logger = LoggerUtils::getChatBotLogger();
        $matched = $message->getExtras('MatchedIntent') instanceof self;
        $logger->info("This is" . ($matched ? "":" not") . " " . $this->getLabel());
        return $matched;
    }

    public function heard(IncomingMessage $message, $next, BotMan $bot) {
        // add records to the log
        $logger = LoggerUtils::getChatBotLogger();
        $logger->info("Replying demographic");
        $bot->replyInThread('Blah Demo',[]);
        return $next($message);
    }


}