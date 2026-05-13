/**
 * PostHog telemetry initialisation.
 *
 * Loaded only when sTelemetryLevel !== 'none' (Header.php injects the bundle
 * conditionally via TelemetryService::isEnabled()). Reads config from
 * window.CRM.telemetry which is set by Header.php before this bundle loads.
 *
 * JS exceptions are captured at all non-none levels (errors / warnings / full).
 * Page views are handled server-side only.
 */

import posthog from "posthog-js";

const cfg = window.CRM && window.CRM.telemetry;
if (cfg && cfg.key && cfg.level && cfg.level !== "none") {
  posthog.init(cfg.key, {
    api_host: cfg.endpoint || "https://eu.i.posthog.com",
    capture_pageview: false,
    autocapture: false,
    capture_heatmaps: false,
    disable_session_recording: true,
    capture_exceptions: true, // active at all levels (errors / warnings / full)
    person_profiles: "never",
    bootstrap: { distinctID: cfg.distinctID || "" },
  });
}
