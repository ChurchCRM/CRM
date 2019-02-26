<?php

interface ChatbotIntent {
    public function getSamples();
    public function getLabel();
    public function getResponse();
}