<?php

/**
 * ChurchCRM scheduled-task runner.
 *
 * This is the supported way to run ChurchCRM's background ("timer") jobs.
 * Without it the jobs only run when somebody loads a page, so a church with no
 * weekday logins sends no scheduled mail on weekdays (issue #9724).
 *
 * What runs here:
 *
 *   - birthday emails (once per day, whenever the first run of the day happens);
 *   - **volunteer reminders** — an email a configurable number of hours before
 *     each volunteer occurrence, set by `iVolunteerReminderLeadHours`
 *     (default 48; 0 turns reminders off);
 *   - **the volunteer notification outbox** — assignment mail, decline and gap
 *     alerts and substitution updates are queued the moment they are decided and
 *     actually delivered here, with failed sends retried on later runs;
 *   - volunteer assignments whose occurrence has finished are marked completed;
 *   - every plugin listening on the CRON_RUN hook.
 *
 * Install a cron entry that runs it hourly, as the same user your web server
 * runs as (so the files it writes stay readable):
 *
 *     0 * * * * /usr/bin/php /path/to/churchcrm/cli/timerjobs.php >> /var/log/churchcrm-cron.log 2>&1
 *
 * Or, from the application's `src` directory:
 *
 *     composer run timerjobs
 *
 * **How often to run it.** Hourly is enough for birthday mail. If you rely on
 * volunteer reminders arriving close to their intended time, run it every 15
 * minutes instead — a reminder is sent by the first run after it falls due, so
 * the cron cadence is the worst-case lateness:
 *
 *     0,15,30,45 * * * * /usr/bin/php /path/to/churchcrm/cli/timerjobs.php > /dev/null 2>&1
 *
 * Installations that cannot run cron at all can have an external monitor POST
 * to the fallback endpoint instead, which needs no ChurchCRM code either:
 *
 *     0,15,30,45 * * * * curl -fsS -X POST -H "x-api-key: <a ChurchCRM API key>" https://example.org/api/background/timerjobs > /dev/null
 *
 * Exit codes: 0 = jobs ran, 1 = bootstrap failed or a job threw. The page-load
 * fallback (POST /api/background/timerjobs, fired from the page footer) keeps
 * working either way, and is rate limited by iTimerJobsMinIntervalMinutes; the
 * command line always runs, because your crontab already decides the cadence.
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\SystemService;

// This file lives under the document root so it ships with every install and
// needs no separate deployment step, so refuse to do anything over HTTP. The
// sibling .htaccess blocks Apache as well; this guard is the server-agnostic
// half of that pair.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$configPath = __DIR__ . '/../Include/Config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "ChurchCRM is not configured yet: {$configPath} is missing. Finish the web installer first.\n");
    exit(1);
}

try {
    // LoadConfigs.php is the shared entry-point bootstrap: it reads Config.php,
    // starts Propel, and initializes SystemConfig.
    require __DIR__ . '/../Include/LoadConfigs.php';

    // Plugins register their CRON_RUN listeners in boot(), which only happens
    // once the plugin manager has loaded them. A web request gets this from
    // Header.php; here we have to ask for it.
    PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');

    // Forced: the administrator's crontab, not the page-load rate limit,
    // decides how often this runs.
    SystemService::runTimerJobs(true);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Timer jobs failed to run: ' . $e->getMessage() . "\n");
    exit(1);
}

// In-memory only — runTimerJobs() collected these, so no database is touched.
$failures = SystemService::getLastTimerJobFailures();
if ($failures !== []) {
    fwrite(STDERR, 'Timer jobs completed with failures: ' . implode(', ', $failures) . "\n");
    exit(1);
}

// The jobs are done by this point and this last read is the only thing here
// that still goes to the database. A connection dropped during a long run (a
// MySQL wait_timeout, say) must not turn a successful night into a cron
// failure, so report the run without the timestamp instead of dying.
try {
    $ranAt = SystemService::getLastTimerJobsRun()?->format('Y-m-d H:i:s') ?? 'unknown';
} catch (\Throwable $e) {
    $ranAt = 'unknown (could not read the run timestamp: ' . $e->getMessage() . ')';
}

fwrite(STDOUT, 'Timer jobs completed at ' . $ranAt . "\n");
exit(0);
