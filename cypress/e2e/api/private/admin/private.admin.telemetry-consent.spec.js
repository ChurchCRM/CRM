/// <reference types="cypress" />

async function captureEvents(request) {
    let body = request.body;
    if (request.query.compression === "gzip-js") {
        const bytes = body instanceof ArrayBuffer ? body : new Uint8Array(body.data || body);
        body = await new Response(new Blob([bytes]).stream().pipeThrough(new DecompressionStream("gzip"))).text();
    }
    if (typeof body === "string") {
        if (body.startsWith("data=")) body = atob(new URLSearchParams(body).get("data"));
        body = JSON.parse(body);
    }
    return Array.isArray(body) ? body : body.batch || [body];
}

// PHP captures run after the response, outside Cypress's browser network interceptor.
function serverCaptures(level) {
    const script = String.raw`
namespace ChurchCRM\Service;
require 'src/vendor/autoload.php';
function curl_init($url) { return new \stdClass(); }
function curl_setopt_array($handle, $options) { $GLOBALS['captures'][] = json_decode($options[CURLOPT_POSTFIELDS], true); return true; }
function curl_exec($handle) { return 'Ok'; }
function curl_getinfo($handle, $option) { return 200; }
putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');
putenv('PHP_TEST_DISPOSABLE_DB');
\ChurchCRM\dto\SystemURLs::init('', [], realpath('src'));
\ChurchCRM\dto\SystemConfig::init();
$configs = new \ReflectionProperty(\ChurchCRM\dto\SystemConfig::class, 'configs');
$values = $configs->getValue();
$value = static fn ($v) => new class($v) { public function __construct(private string $v) {} public function getValue() { return $this->v; } public function setValue($v) { $this->v = $v; } public function getType() { return 'internal'; } };
$values['sTelemetryLevel'] = $value('${level}');
$values['sSystemID'] = $value('cypress-telemetry');
$values['sTelemetryLifecycleVersion'] = $value(\ChurchCRM\Utils\VersionUtils::getInstalledVersion());
$values['sTelemetryHeartbeatDate'] = $value('2000-01-01');
$configs->setValue(null, $values);
$idPath = sys_get_temp_dir() . '/cypress-telemetry-' . bin2hex(random_bytes(8)) . '.php';
(new \ReflectionProperty(\ChurchCRM\Utils\SystemIdentity::class, 'path'))->setValue(null, $idPath);
$GLOBALS['captures'] = [];
TelemetryService::capturePageView('/admin/system/debug');
$handler = new \ChurchCRM\Logging\PostHogLogHandler();
foreach (['/people/person/123?personId=456', '/api/person/%31%32%33/photo/456?token=private', '/api/person/private@example.org/token/private-token'] as $url) {
    $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'app', \Monolog\Level::Error, 'Cypress telemetry ' . bin2hex(random_bytes(8)), extra: ['url' => $url]));
}
$capture = new \ReflectionMethod(TelemetryService::class, 'capture');
$send = static fn ($event, $properties) => $capture->invoke(null, $event, $properties);
TelemetryService::syncLifecycle($send);
TelemetryService::syncLifecycle($send);
@unlink($idPath);
echo json_encode($GLOBALS['captures']);`;
    const command = `php -r 'eval(base64_decode("${btoa(script)}"));'`;
    // CI's host PHP differs from the application runtime in the selected Compose service.
    return cy.exec(`if [ "$GITHUB_ACTIONS" = "true" ]; then
        docker compose -f docker/docker-compose.yaml -f docker/docker-compose.parallel.yaml exec -T -w /home/ChurchCRM "webserver-$DEPLOYMENT" ${command}
    else
        ${command}
    fi`).then(({ stdout }) => JSON.parse(stdout));
}

describe("Admin chooses whether to share page usage", () => {
    const original = {};
    const keys = ["sTelemetryLevel", "sTelemetryAskedVersion", "sTelemetryLifecycleVersion"];

    before(() => {
        keys.forEach((key) => cy.getSystemConfig(key).then((value) => { original[key] = value; }));
    });

    after(() => {
        keys.forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    it("persists the single toggle; both tiers send errors and only Full sends page views", () => {
        cy.setSystemConfig("sTelemetryLevel", "default");
        cy.setupAdminSession();
        const events = [];
        const scripts = [];
        cy.intercept("https://us.i.posthog.com/**", async (request) => {
            if (/\/(e|batch)(\/|\?)/.test(request.url)) {
                events.push(...await captureEvents(request));
                request.reply({ statusCode: 200, body: "Ok" });
            } else {
                request.reply({ statusCode: 200, body: { capturePerformance: { web_vitals: true } } });
            }
        });
        cy.readFile("node_modules/posthog-js/dist/exception-autocapture.js").then((body) => {
            cy.intercept("https://us-assets.i.posthog.com/**", (request) => {
                scripts.push(request.url);
                if (request.url.includes("exception-autocapture")) {
                    request.reply({ statusCode: 200, headers: { "content-type": "application/javascript" }, body });
                } else {
                    request.reply({ statusCode: 200, body: { capturePerformance: { web_vitals: true } } });
                }
            });
        });
        // Only the browser response leaves test mode; the server stays isolated from PostHog.
        cy.intercept("GET", "**/admin/system/debug", (request) => {
            request.continue((response) => {
                response.body = response.body.replaceAll('"install_profile":"test"', '"install_profile":"production"');
            });
        });
        cy.on("uncaught:exception", (error) => {
            if (error.message.includes("Telemetry verification probe")) return false;
        });
        const assertException = (level) => {
            cy.window().should((win) => {
                expect(win.__PosthogExtensions__?.errorWrappingFunctions).to.exist;
                expect(win.onerror?.__POSTHOG_INSTRUMENTED__, "PostHog onerror wrapper").to.eq(true);
            });
            cy.window().then((win) => {
                events.length = 0;
                const message = `Telemetry verification probe ${level}`;
                const error = new win.Error(message);
                // Keep the probe stack on an application URL.
                error.stack = `Error: ${message}\n    at telemetryProbe (${win.location.origin}/people/person/%31%32%33:1:1)`;
                win.onerror(message, win.location.href, 1, 1, error);
            });
            cy.wrap(null, { timeout: 15000 }).should(() => {
                const exception = events.find((event) => event.event === "$exception");
                expect(exception, `browser exception at ${level}`).to.exist;
                expect(exception.properties).to.include.keys("crm_version", "php_version", "database_version", "locale", "install_path_type");
                expect(exception.properties.$exception_list[0].stacktrace.frames[0].filename).to.eq("/people/person/{id}");
                expect(events.map((event) => event.event)).not.to.include("$web_vitals");
                expect(scripts.some((url) => url.includes("web-vitals"))).to.eq(false);
            });
            serverCaptures(level).then((captures) => {
                captures.filter((capture) => capture.event === "log_error").forEach((capture) => {
                    expect(capture.properties).not.to.have.property("route");
                    expect(JSON.stringify(capture)).not.to.match(/personId|private@example|private-token|%31%32%33/);
                });
                expect(captures.map((capture) => capture.event)).to.deep.eq(level === "full" ? ["page_view", "log_error", "log_error", "log_error", "heartbeat"] : ["log_error", "log_error", "log_error", "heartbeat"]);
            });
        };
        cy.on("window:before:load", (win) => {
                // PostHog's bot filter blocks the Cypress user agent before any capture.
                Object.defineProperty(win.navigator, "userAgent", { value: win.navigator.userAgent.replace(/Cypress\/[\d.]+/gi, "").replace(/HeadlessChrome/g, "Chrome") });
                Object.defineProperty(win.navigator, "webdriver", { value: false });
                Object.defineProperty(win.navigator, "userAgentData", { value: undefined });
        });
        cy.visit("/admin/system/debug#telemetry");
        cy.get("#telemetry input").should("have.length", 1);
        cy.get("#telemetry-page-usage").should("not.be.checked");
        cy.get("#telemetry").contains("Default");
        cy.get("#telemetry").contains("Share which pages are used");
        assertException("default");
        cy.get("#telemetry-page-usage").check();
        cy.get("#telemetry-page-usage").should("be.checked").and("not.be.disabled");
        cy.get("#telemetry").contains("Full");
        cy.request("/admin/api/system/config/sTelemetryLevel").its("body.value").should("eq", "full");
        assertException("full");
        cy.get("#telemetry-page-usage").uncheck();
        cy.get("#telemetry-page-usage").should("not.be.checked").and("not.be.disabled");
        cy.request("/admin/api/system/config/sTelemetryLevel").its("body.value").should("eq", "default");
        cy.reload();
        cy.get("#telemetry-page-usage").should("not.be.checked");
    });

    it("maps old and unknown consent values to active Default", () => {
        ["none", "install", "errors", "warnings", "invalid", undefined].forEach((level) => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/system/telemetry-consent", { level }, 200);
            cy.getSystemConfig("sTelemetryLevel").should("eq", "default");
        });
        cy.getSystemConfig("sTelemetryAskedVersion").should("not.be.empty");
    });

    it("keeps Default active when the admin declines page usage", () => {
        cy.setSystemConfig("sTelemetryLevel", "default");
        cy.setSystemConfig("sTelemetryAskedVersion", "");
        cy.setupAdminSession();
        cy.visit("/admin/");
        cy.get('#telemetry-consent-card [data-level="default"]').contains("No thanks").click();
        cy.get("#telemetry-consent-card").should("not.exist");
        cy.request("/admin/api/system/config/sTelemetryLevel").its("body.value").should("eq", "default");
        cy.reload();
        cy.get("#telemetry-consent-card").should("not.exist");
        cy.window().its("CRM.telemetry.install_profile").should("eq", "test");
    });

    it("denies non-admin changes", () => {
        cy.setSystemConfig("sTelemetryLevel", "default");
        cy.makePrivateUserAPICall("POST", "/admin/api/system/telemetry-consent", { level: "full" }, 403);
        cy.getSystemConfig("sTelemetryLevel").should("eq", "default");
    });
});
