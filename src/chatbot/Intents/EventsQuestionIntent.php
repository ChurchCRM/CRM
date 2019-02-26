<?php

require_once __DIR__."/ChatbotIntent.php";
Class EventsQuestionIntent implements ChatbotIntent {
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
}