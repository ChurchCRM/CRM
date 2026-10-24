<?php

namespace ChurchCRM\Utils;

use Throwable;

/**
 * Work that runs after the browser has the whole response, such as sending an
 * email the response must not wait for.
 *
 * Callables queued during a request run in order from a shutdown function, which
 * PHP calls after the entry point's `$app->run()` has emitted the response. The
 * response is finished first: by the SAPI's own call under PHP-FPM or FrankenPHP,
 * otherwise (Apache mod_php) by sending it with a Content-Length and
 * `Connection: close` so the client stops reading. On the command line the work
 * runs at once. Every callable is caught and logged; none can throw.
 */
final class AfterResponse
{
    /** @var list<callable(): void> */
    private static array $queue = [];
    private static bool $finishing = false;

    public static function defer(callable $work): void
    {
        if (self::isCommandLine()) {
            self::runSafely($work);

            return;
        }

        self::finishEarly();
        self::$queue[] = $work;
    }

    /**
     * Finishes this request's response before the shutdown work whether or not
     * any work is queued, so the response looks and times the same either way.
     */
    public static function finishEarly(): void
    {
        if (self::$finishing || self::isCommandLine()) {
            return;
        }

        self::$finishing = true;
        // Holds the emitted response back from the web server, so finish() can still set its length.
        ob_start();
        register_shutdown_function(self::runQueued(...));
    }

    private static function runQueued(): void
    {
        self::runSafely(self::finish(...));
        while (($work = array_shift(self::$queue)) !== null) {
            self::runSafely($work);
        }
    }

    private static function finish(): void
    {
        ignore_user_abort(true);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();

            return;
        }
        if (function_exists('frankenphp_finish_request')) {
            frankenphp_finish_request();

            return;
        }

        $body = self::takeBufferedOutput();
        if ($body !== null) {
            header('Content-Length: ' . strlen($body));
            header('Connection: close');
            echo $body;
        }
        flush();
    }

    /**
     * Everything still buffered, outermost buffer first. Null when the headers
     * are already out or a buffer has a handler (such as compression) that must
     * see the output: the response then ends with the request, after the work.
     */
    private static function takeBufferedOutput(): ?string
    {
        if (headers_sent()) {
            return null;
        }
        foreach (ob_get_status(true) as $buffer) {
            if ($buffer['name'] !== 'default output handler' || ($buffer['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
                return null;
            }
        }

        $body = '';
        while (ob_get_level() > 0) {
            $body = ob_get_clean() . $body;
        }

        return $body;
    }

    private static function runSafely(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->error('Work deferred until after the response failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private static function isCommandLine(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}
