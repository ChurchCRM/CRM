<?php

namespace ChurchCRM\Logging;

use ChurchCRM\Service\TelemetryService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Forwards Monolog entries to PostHog as log_error events.
 *
 * Level mapping:
 *   LEVEL_ERRORS   → ERROR and CRITICAL only
 *   LEVEL_WARNINGS → WARNING, ERROR, CRITICAL
 *   LEVEL_FULL     → same as LEVEL_WARNINGS (page views are handled separately)
 *
 * Never throws — file logging must be unaffected by PostHog outages.
 */
class PostHogLogHandler extends AbstractProcessingHandler
{
    public function __construct()
    {
        // Accept WARNING and above; write() applies finer-grained level filtering.
        parent::__construct(Level::Warning);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $telemetryLevel = TelemetryService::getLevel();

            if ($telemetryLevel === TelemetryService::LEVEL_NONE) {
                return;
            }

            // 'errors' mode: only forward ERROR and CRITICAL, skip WARNING.
            if ($telemetryLevel === TelemetryService::LEVEL_ERRORS
                && $record->level->value < Level::Error->value) {
                return;
            }

            TelemetryService::captureLogEvent($record);
        } catch (\Throwable $e) {
            error_log('PostHogLogHandler::write failed: ' . $e->getMessage());
        }
    }
}
