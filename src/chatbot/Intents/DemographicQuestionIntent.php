<?php

require_once __DIR__."/ChatbotIntent.php";
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
}