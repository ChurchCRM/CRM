/**
 * PostHog telemetry initialisation.
 *
 * Loaded only when bEnableTelemetry is true (Header.php injects the bundle
 * conditionally). Reads key/endpoint from window.CRM.telemetry which is set
 * by Header.php before this bundle loads.
 *
 * Configuration:
 *   - exceptions-only autocapture (no clicks, no session replay, no pageviews)
 *   - distinctID bootstrapped from sSystemID so it correlates with server events
 */

import posthog from "posthog-js";

const cfg = window.CRM && window.CRM.telemetry;
if (cfg && cfg.key) {
  posthog.init(cfg.key, {
    api_host: cfg.endpoint || "https://eu.i.posthog.com",
    capture_pageview: false,
    autocapture: false,
    capture_heatmaps: false,
    disable_session_recording: true,
    capture_exceptions: true,
    person_profiles: "never",
    bootstrap: { distinctID: cfg.distinctID || "" },
  });
}
