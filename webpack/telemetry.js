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

const cfg = window.CRM?.telemetry;
// Chrome reports this when a ResizeObserver callback changes layout before
// every notification in the frame is delivered. FullCalendar's own observer
// does that; it is not an application exception.
function isBenignResizeObserverLoop(event) {
  if (event?.event !== "$exception") {
    return false;
  }
  const list = event.properties?.$exception_list || [];
  return list.some((item) => {
    const text = `${item?.value || ""} ${item?.type || ""}`;
    return text.includes("ResizeObserver loop");
  });
}

if (cfg?.key && cfg.level && cfg.level !== "none") {
  posthog.init(cfg.key, {
    api_host: cfg.endpoint,
    capture_pageview: false,
    autocapture: false,
    capture_heatmaps: false,
    disable_session_recording: true,
    capture_exceptions: true, // active at all levels (errors / warnings / full)
    person_profiles: "never",
    bootstrap: { distinctID: cfg.distinctID || "" },
    before_send: (event) => (isBenignResizeObserverLoop(event) ? null : event),
  });
}
