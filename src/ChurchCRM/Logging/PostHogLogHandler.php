<?php

namespace ChurchCRM\Logging;

use ChurchCRM\Service\TelemetryService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Forwards Monolog WARNING+ entries to PostHog as log_error events.
 * Only active when bEnableTelemetry is true. Never throws — file logging
 * must be unaffected by PostHog outages or network errors.
 */
class PostHogLogHandler extends AbstractProcessingHandler
{
    public function __construct()
    {
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
