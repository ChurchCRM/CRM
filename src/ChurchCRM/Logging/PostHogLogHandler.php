<?php

namespace ChurchCRM\Logging;

use ChurchCRM\Service\TelemetryService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Forwards Monolog entries to PostHog as log_error events.
 *
 * Default and Full forward WARNING and above.
 *
 * Never throws — file logging must be unaffected by PostHog outages.
 */
class PostHogLogHandler extends AbstractProcessingHandler
{
    public function __construct()
    {
        // Accept WARNING and above at both tiers.
        parent::__construct(Level::Warning);
    }

    protected function write(LogRecord $record): void
    {
        try {
            TelemetryService::captureLogEvent($record);
        } catch (\Throwable $e) {
            error_log('PostHogLogHandler::write failed: ' . $e->getMessage());
        }
    }
}
