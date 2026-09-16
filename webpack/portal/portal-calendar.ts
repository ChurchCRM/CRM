/**
 * Member Portal — the calendar page (MP5, #9866).
 *
 * The same FullCalendar v7 setup the public calendar uses, with three
 * differences a member's calendar needs:
 *
 *   1. it is read-only — nothing is draggable, selectable or clickable through
 *      to an editor, and the server never sends an event URL;
 *   2. the events come from one session-gated endpoint,
 *      GET /api/portal/calendar/events, which answers the union of whatever
 *      the administrator has switched on, already coloured per calendar;
 *   3. a click opens the detail panel below the grid rather than a dialog, so
 *      a phone simply scrolls to it.
 *
 * Mobile first: below the tablet breakpoint the list view is what opens, since
 * a month grid on a 375px screen is unreadable.
 *
 * temporal-polyfill must be imported first: it sets globalThis.Temporal, which
 * FullCalendar reads at module load.
 */
import "temporal-polyfill/global";
import { Calendar } from "fullcalendar/all";
import formaTheme from "fullcalendar/themes/forma";
import "fullcalendar/skeleton.css";
import "fullcalendar/themes/forma/theme.css";
import "fullcalendar/themes/forma/palettes/blue.css";
import { applyFcLocale } from "../common/fc-locale";

/**
 * `fullcalendar/all` exports only `Calendar`, so the shapes FullCalendar hands
 * a callback are described here, narrowed to the parts this page reads. Where a
 * callback is passed straight to the Calendar constructor its parameters are
 * left to contextual typing instead, so FullCalendar's own (unexported) types
 * still check the call.
 */
interface FcEventSourceArg {
  startStr: string;
  endStr: string;
}

interface FcEventClickArg {
  jsEvent: { preventDefault(): void };
  event: {
    title: string;
    start: Date | null;
    end: Date | null;
    allDay: boolean;
    extendedProps: Record<string, unknown>;
  };
}

/** What `src/portal/routes/calendar.php` puts on the page. */
interface PortalCalendarConfig {
  eventsUrl: string;
  timeZone: string;
  maxWindowDays: number;
}

/** One event as PortalCalendarService shapes it. */
interface PortalCalendarEvent {
  id: string;
  title: string;
  start: string;
  end: string | null;
  allDay: boolean;
  color: string | null;
  contrastColor: string | null;
  extendedProps: {
    calendarName?: string;
    calendarType?: string;
    calendarId?: number;
    description?: string;
    location?: string;
  };
}

const GRID_ID = "portal-calendar";
const DETAIL_ID = "portal-calendar-detail";

/** Below this width the list view is the one that opens (responsive-design-guidelines.md). */
const TABLET_BREAKPOINT = 768;

/**
 * Translate through the page's global i18next — the one the layout loads and
 * the locale loader initialises. Importing the npm package would bundle a
 * second, never-initialised instance whose `t()` returns an empty string. The
 * English literal is the fallback, so the page reads correctly before the
 * locale files arrive and when a key has no translation.
 */
function t(text: string): string {
  const translated = typeof i18next !== "undefined" ? i18next.t(text) : "";
  return translated || text;
}

function config(): PortalCalendarConfig | null {
  const raw = window.CRM?.portalCalendar as PortalCalendarConfig | undefined;
  return raw && typeof raw.eventsUrl === "string" ? raw : null;
}

function element(id: string): HTMLElement | null {
  return document.getElementById(id);
}

/** Show a row of the detail panel, or hide it when there is nothing to show. */
function setDetailRow(rowId: string, valueId: string, value: string): void {
  const row = element(rowId);
  const cell = element(valueId);
  if (cell) {
    cell.textContent = value;
  }
  if (row) {
    row.hidden = value === "";
  }
}

/**
 * Ask the portal endpoint for the window FullCalendar is showing. The dates go
 * as plain days because that is what the endpoint takes — sending an instant
 * would invite the browser's own time zone into a church-time calendar.
 */
async function fetchEvents(url: string, info: FcEventSourceArg): Promise<PortalCalendarEvent[]> {
  const query = new URLSearchParams({
    from: info.startStr.slice(0, 10),
    to: info.endStr.slice(0, 10),
  });

  const response = await fetch(`${url}?${query.toString()}`, {
    credentials: "same-origin",
    headers: { accept: "application/json" },
  });
  if (!response.ok) {
    throw new Error(`portal calendar events: ${response.status}`);
  }

  const body = (await response.json()) as { events?: PortalCalendarEvent[] };
  return Array.isArray(body.events) ? body.events : [];
}

/**
 * Fill and reveal the detail panel for one event.
 *
 * Times are formatted by FullCalendar itself, which applies the calendar's
 * locale and its configured time zone — so a member reading from another
 * country still sees the time the church means (timezone-handling.md).
 */
function openDetail(calendar: Calendar, info: FcEventClickArg): void {
  const panel = element(DETAIL_ID);
  if (!panel) {
    return;
  }

  const event = info.event;
  const props = (event.extendedProps ?? {}) as PortalCalendarEvent["extendedProps"];

  const title = element("portal-calendar-detail-title");
  if (title) {
    title.textContent = event.title;
  }

  const dateFormat = { weekday: "long", year: "numeric", month: "long", day: "numeric" } as const;
  const timeFormat = { hour: "2-digit", minute: "2-digit" } as const;

  let when = "";
  if (event.start) {
    when = calendar.formatDate(event.start, dateFormat);
    if (!event.allDay) {
      const startTime = calendar.formatDate(event.start, timeFormat);
      const endTime = event.end ? calendar.formatDate(event.end, timeFormat) : "";
      when = `${when}, ${startTime}${endTime ? ` – ${endTime}` : ""}`;
    }
  }

  setDetailRow("portal-calendar-detail-when-row", "portal-calendar-detail-when", when);
  setDetailRow("portal-calendar-detail-where-row", "portal-calendar-detail-where", props.location ?? "");
  setDetailRow("portal-calendar-detail-calendar-row", "portal-calendar-detail-calendar", props.calendarName ?? "");
  setDetailRow("portal-calendar-detail-description-row", "portal-calendar-detail-description", props.description ?? "");

  panel.hidden = false;
  panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

function wireDetailClose(): void {
  element("portal-calendar-detail-close")?.addEventListener("click", () => {
    const panel = element(DETAIL_ID);
    if (panel) {
      panel.hidden = true;
    }
  });
}

function initCalendar(): void {
  const settings = config();
  const grid = element(GRID_ID);
  if (!settings || !grid) {
    return;
  }

  // The placeholder paragraph has done its job once FullCalendar takes over.
  grid.textContent = "";

  const isNarrow = window.matchMedia(`(max-width: ${TABLET_BREAKPOINT - 1}px)`).matches;

  const calendar = new Calendar(grid, {
    plugins: [formaTheme],
    initialView: isNarrow ? "listMonth" : "dayGridMonth",
    headerToolbar: {
      start: "prev,next today",
      center: "title",
      end: "dayGridMonth,timeGridWeek,listMonth",
    },
    // FullCalendar 7 hashes its own class names, so its toolbar buttons cannot be
    // reached from a stylesheet without this. The portal needs to: FullCalendar's
    // buttons are 34px tall and the responsive guidelines ask for 44px on a page a
    // member scrolls with a thumb (#9869). Same hook the admin calendar uses.
    headerToolbarClass: "portal-fc-toolbar",
    // The toolbar's button labels come from FullCalendar's own locale file,
    // which applyFcLocale() loads for the church's language.
    height: "auto",
    // Read-only, every way FullCalendar understands the word.
    editable: false,
    selectable: false,
    dayMaxEvents: true,
    locale: (window.CRM?.lang as string) || "en",
    timeZone: settings.timeZone || "local",
    noEventsText: t("Nothing is on the calendar for this period."),
    events: (info, success, failure) => {
      fetchEvents(settings.eventsUrl, info)
        .then((events) => success(events as never))
        .catch((error: Error) => failure(error));
    },
    eventClick: (info) => {
      // The server sends no URLs, but a theme's own event source might; either
      // way a member is never navigated away from their calendar.
      info.jsEvent.preventDefault();
      openDetail(calendar, info);
    },
  });

  window.CRM = window.CRM || {};
  window.CRM.fullcalendar = calendar;

  wireDetailClose();

  applyFcLocale(calendar).then(() => {
    calendar.render();
  });
}

function start(): void {
  if (typeof window.CRM?.onLocalesReady === "function") {
    window.CRM.onLocalesReady(initCalendar);
  } else {
    initCalendar();
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
