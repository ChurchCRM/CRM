<?php

use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\Interfaces\Middleware\Sending;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use Monolog\Handler\StreamHandler;
use ChurchCRM\Event;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
class SendingLoggerMiddleware implements Sending
{

    /**
     * Handle an outgoing message payload before/after it
     * hits the message service.
     *
     * @param mixed $payload
     * @param callable $next
     * @param BotMan $bot
     *
     * @return mixed
     */
    public function sending($payload, $next, BotMan $bot)
    {
        LoggerUtils::getChatBotLogger()->info("Outgoing message: " . print_r($payload,true));
        return $next($payload);
    }

   
}