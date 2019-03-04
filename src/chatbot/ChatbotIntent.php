<?php

use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Interfaces\Middleware\Matching;
use BotMan\BotMan\Interfaces\Middleware\Heard;
use BotMan\BotMan\BotMan;
use ChurchCRM\Utils\LoggerUtils;

abstract class ChatbotIntent implements Matching, Heard{
    abstract public function getSamples();
    abstract public function getLabel();
    abstract public function getResponse();
    public function matching(IncomingMessage $message, $pattern, $regexMatched) {
        $logger = LoggerUtils::getChatBotLogger();
        $matched = $message->getExtras('MatchedIntent')->getLabel() == $this->getLabel();
        $logger->info("This is" . ($matched ? "":" not") . " " . $this->getLabel());
        return $matched;
    }

}