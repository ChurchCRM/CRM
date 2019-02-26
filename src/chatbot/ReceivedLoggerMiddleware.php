<?php

use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\Interfaces\Middleware\Received;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use Monolog\Handler\StreamHandler;
use ChurchCRM\Event;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class ReceivedLoggerMiddleware implements Received
{
    /**
     * Handle an incoming message.
     *
     * @param IncomingMessage $message
     * @param callable $next
     * @param BotMan $bot
     *
     * @return mixed
     */
    public function received(IncomingMessage $message, $next, BotMan $bot)
    {
        LoggerUtils::getChatBotLogger()->info("Incoming message: " . $message->getText());
        return $next($message);
    }
}