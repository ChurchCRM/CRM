/// <reference types="cypress" />

/**
 * POST /api/background/timerjobs under concurrency — regression coverage for
 * issue #9727.
 *
 * BirthdayEmailService::run() guarded duplicate sends with a check-then-set on
 * the `sLastBirthdayEmailRunDate` config value. Footer.js fires the timer-job
 * endpoint on every page load, so several requests routinely passed the check
 * together and then collided on the `config_cfg` primary key: 7 of 8 concurrent
 * requests returned HTTP 500, and because the exception escaped
 * SystemService::runTimerJobs() they also skipped the CRON_RUN hook.
 *
 * The requests are fired with fetch(..., { credentials: "omit" }) so each one
 * gets its own PHP session — a shared session is serialised by PHP's session
 * lock and cannot reproduce the race.
 */
describe("API Private Admin - Background Timer Jobs concurrency", () => {
    const CONCURRENT_REQUESTS = 8;
    const MARKER = "sLastBirthdayEmailRunDate";
    const FEATURE = "bEnableBirthdayEmails";
    // #9724 added a server-side rate limit to the page-load trigger. It would
    // skip all but the first of these concurrent requests, which is exactly
    // what it is for — but it would also hide the #9727 race this spec exists
    // to catch, so the limit is disabled for the duration.
    const RATE_LIMIT = "iTimerJobsMinIntervalMinutes";
    const TIMEZONE = "sTimeZone";

    let originalMarker;
    let originalFeature;
    let originalRateLimit;
    let configuredTimezone;

    const configUrl = (name) => `/admin/api/system/config/${name}`;

    /**
     * Absolute URL of the timer-job endpoint.
     *
     * cy.request()/cy.visit() concatenate baseUrl onto a relative URL, but
     * win.fetch() is the browser's own fetch: it resolves a root-relative path
     * against the page *origin*, which drops the install's base path. On the
     * subdirectory profile ("http://host/churchcrm/") the literal
     * "/api/background/timerjobs" therefore reached http://host/api/... and
     * 404'd. Composing the URL from baseUrl keeps the base path, and stripping
     * the trailing slash first avoids a "//api/..." double slash at the root.
     */
    const timerJobsUrl = () =>
        `${Cypress.config("baseUrl").replace(/\/+$/, "")}/api/background/timerjobs`;

    const readConfig = (name) =>
        cy
            .makePrivateAdminAPICall("GET", configUrl(name), null, 200)
            .then((response) => response.body.value);

    const writeConfig = (name, value) =>
        cy.makePrivateAdminAPICall("POST", configUrl(name), { value: value }, 200);

    /**
     * Today's date in the app's configured timezone, as the service formats it.
     *
     * BirthdayEmailService builds the marker from
     * DateTimeUtils::getConfiguredTimezone(), i.e. the `sTimeZone` system
     * setting. `new Date()` on the Cypress runner gives the runner's local
     * wall-clock date instead (UTC in CI), so the two disagree on the calendar
     * date whenever the runner and the install sit on different sides of
     * midnight. Derive the expected string from `sTimeZone`, read once in
     * before(). "en-CA" formats as ISO-style YYYY-MM-DD.
     */
    const todayString = () =>
        new Intl.DateTimeFormat("en-CA", {
            timeZone: configuredTimezone,
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
        }).format(new Date());

    /**
     * Fire CONCURRENT_REQUESTS timer-job requests in parallel and resolve with
     * the list of HTTP status codes.
     */
    const fireConcurrentTimerJobs = () =>
        cy.window().then((win) => {
            const apiKey = Cypress.env("admin.api.key");
            const calls = Array.from({ length: CONCURRENT_REQUESTS }, () =>
                win.fetch(timerJobsUrl(), {
                    method: "POST",
                    headers: { "x-api-key": apiKey },
                    // Each request must get its own PHP session, or the session
                    // lock serialises them and there is no race to test.
                    credentials: "omit",
                }),
            );
            return Promise.all(calls).then((responses) =>
                responses.map((response) => response.status),
            );
        });

    before(() => {
        readConfig(TIMEZONE).then((value) => {
            // Empty falls back to the runner's local zone, which is what the
            // assertion used before — never worse than the old behaviour.
            configuredTimezone = value || undefined;
        });
        readConfig(MARKER).then((value) => {
            originalMarker = value;
        });
        readConfig(FEATURE).then((value) => {
            originalFeature = value;
        });
        readConfig(RATE_LIMIT).then((value) => {
            originalRateLimit = value;
        });
    });

    beforeEach(() => {
        // Same-origin page so fetch() can reach the API; no session needed
        // because the requests authenticate with the admin API key.
        cy.visit("/session/begin");
        writeConfig(FEATURE, "1");
        writeConfig(RATE_LIMIT, "0");
    });

    after(() => {
        writeConfig(MARKER, originalMarker ?? "");
        writeConfig(FEATURE, originalFeature ?? "0");
        writeConfig(RATE_LIMIT, originalRateLimit ?? "15");
    });

    it("returns 200 for every concurrent request when the run marker is unset", () => {
        // The #9727 reproduction: no marker row, so every request races to
        // INSERT it and all but one used to fail with a duplicate-key 500.
        writeConfig(MARKER, "");

        fireConcurrentTimerJobs().then((statuses) => {
            expect(statuses).to.have.length(CONCURRENT_REQUESTS);
            statuses.forEach((status) => expect(status).to.equal(200));
        });

        // Exactly one request claimed the day.
        readConfig(MARKER).should("equal", todayString());
    });

    it("returns 200 for every concurrent request when the run marker is stale", () => {
        // A NULL cfg_value is the same "stale marker" case and used to block
        // birthday emails for good, because SQL evaluates `NULL <> :today` as
        // UNKNOWN so the conditional UPDATE matched nothing. It cannot be set up
        // from here: POSTing a config value never writes NULL (an empty value
        // equals the default, which deletes the row), and Cypress has no raw-SQL
        // task in this project. The claim now filters
        // `cfg_value <> :today OR cfg_value IS NULL`; the NULL path is covered
        // by direct-SQL verification on the dev stack.
        writeConfig(MARKER, "2000-01-01");

        fireConcurrentTimerJobs().then((statuses) => {
            statuses.forEach((status) => expect(status).to.equal(200));
        });

        readConfig(MARKER).should("equal", todayString());
    });

    it("returns 200 for every concurrent request when the run already happened today", () => {
        writeConfig(MARKER, todayString());

        fireConcurrentTimerJobs().then((statuses) => {
            statuses.forEach((status) => expect(status).to.equal(200));
        });

        readConfig(MARKER).should("equal", todayString());
    });
});
