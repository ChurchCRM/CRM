<?php

use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\BotMan;

Class DemographicQuestionIntent extends ChatbotIntent {
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
   
    public function heard(IncomingMessage $message, $next, BotMan $bot) {
        // add records to the log
        $logger = LoggerUtils::getChatBotLogger();
        $logger->info("Replying demographic");
        $bot->replyInThread('Blah Demo',[]);
        return $next($message);
    }


}