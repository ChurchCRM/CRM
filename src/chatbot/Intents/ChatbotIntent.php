<?php

use BotMan\BotMan\Interfaces\Middleware\Matching;
use BotMan\BotMan\Interfaces\Middleware\Heard;
use BotMan\BotMan\BotMan;

interface ChatbotIntent extends Matching, Heard{
    public function getSamples();
    public function getLabel();
    public function getResponse();
}