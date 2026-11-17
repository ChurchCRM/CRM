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

function stripUrlDetails(value) {
  if (typeof value !== "string") {
    return value;
  }

  try {
    const url = new URL(value, window.location.origin);
    const rootPath = (window.CRM?.root || "").replace(/\/+$/, "");
    if (rootPath && (url.pathname === rootPath || url.pathname.startsWith(`${rootPath}/`))) {
      url.pathname = url.pathname.slice(rootPath.length) || "/";
    }
    return url.pathname;
  } catch {
    return value.split(/[?#]/, 1)[0];
  }
}

// Stack frames and messages carry this installation's address; replace it wherever it appears.
function scrubHost(value, replacements) {
  if (typeof value === "string") {
    return replacements.reduce((text, [needle, replacement]) => text.split(needle).join(replacement), value);
  }
  if (Array.isArray(value)) {
    return value.map((item) => scrubHost(item, replacements));
  }
  if (value && typeof value === "object") {
    for (const key of Object.keys(value)) {
      value[key] = scrubHost(value[key], replacements);
    }
  }
  return value;
}

function sanitizeEvent(event) {
  if (isBenignResizeObserverLoop(event)) {
    return null;
  }

  const properties = event?.properties;
  if (!properties) {
    return event;
  }

  delete properties.$ip;
  for (const property of Object.keys(properties)) {
    if (property.startsWith("$geoip") || /(host|referrer|referring_domain)$/.test(property)) {
      delete properties[property];
    }
  }
  properties.$geoip_disable = true;
  const rootPath = (window.CRM?.root || "").replace(/\/+$/, "");
  if (typeof properties.$pathname === "string") {
    const path = properties.$pathname.split(/[?#]/, 1)[0];
    properties.$pathname =
      rootPath && (path === rootPath || path.startsWith(`${rootPath}/`)) ? path.slice(rootPath.length) || "/" : path;
  }
  for (const property of Object.keys(properties)) {
    if (/(^|_)url$/.test(property)) {
      properties[property] = stripUrlDetails(properties[property]);
    }
  }
  const { origin, host, hostname } = window.location;
  const replacements = [
    [`${origin}${rootPath}`, ""],
    [origin, ""],
    [host, "[host]"],
    [hostname, "[host]"],
  ].filter(([needle]) => needle && needle !== "null");
  event.properties = scrubHost(properties, replacements);

  return event;
}

if (cfg?.key && cfg.level && cfg.level !== "none" && cfg.install_profile !== "test") {
  posthog.init(cfg.key, {
    api_host: cfg.endpoint,
    capture_pageview: false,
    capture_performance: false,
    autocapture: false,
    capture_heatmaps: false,
    disable_session_recording: true,
    capture_exceptions: true, // active at all levels (errors / warnings / full)
    person_profiles: "never",
    ip: false,
    bootstrap: { distinctID: cfg.distinctID || "" },
    before_send: sanitizeEvent,
  });
  const superProperties = {
    install_profile: cfg.install_profile,
    install_path_type: window.CRM?.root && window.CRM.root !== "/" ? "subdirectory" : "root",
  };
  if (window.CRM?.version) {
    superProperties.crm_version = window.CRM.version;
  }
  posthog.register(superProperties);
}
