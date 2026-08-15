// FullCalendar v7 — bundled via webpack (ESM imports) for the public (unauthenticated) calendar.
// temporal-polyfill must come first: it sets globalThis.Temporal, which FC reads at module load.
import "temporal-polyfill/global";
import { Calendar } from "fullcalendar/all";
import formaTheme from "fullcalendar/themes/forma";
import "fullcalendar/skeleton.css";
import "fullcalendar/themes/forma/theme.css";
import "fullcalendar/themes/forma/palettes/blue.css";
import { applyFcLocale } from "./common/fc-locale";

document.addEventListener("DOMContentLoaded", () => {
  // Server-side data is passed via window.CRM.externalCalendarArgs set by calendar.php.
  const args = window.CRM.externalCalendarArgs || {};
  const eventSource = args.eventSource || "";
  const timeZone = args.timeZone || "local";

  function initCalendar() {
    window.CRM.fullcalendar = new Calendar(document.getElementById("calendar"), {
      plugins: [formaTheme],
      headerToolbar: {
        start: "prev,next today",
        center: "title",
        end: "dayGridMonth,timeGridWeek,timeGridDay,listMonth",
      },
      height: 600,
      selectable: false,
      editable: false,
      selectMirror: true,
      locale: window.CRM.lang || "en",
      timeZone: timeZone,
      eventSources: [eventSource],
      eventClick: (info) => {
        info.jsEvent.preventDefault(); // prevent FullCalendar from following event.url

        const event = info.event;
        const props = event.extendedProps || {};

        document.getElementById("eventDetailModalLabel").textContent = event.title;

        // Use FullCalendar's own formatter — it applies the calendar's locale and timezone
        // so times are always shown in the church timezone regardless of the visitor's browser.
        const cal = window.CRM.fullcalendar;
        const dateFmt = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
        const timeFmt = { hour: "2-digit", minute: "2-digit" };
        let timeStr = "";
        if (event.allDay) {
          timeStr = event.start ? cal.formatDate(event.start, dateFmt) : "";
        } else {
          const dateStr = event.start ? cal.formatDate(event.start, dateFmt) : "";
          const startTime = event.start ? cal.formatDate(event.start, timeFmt) : "";
          const endTime = event.end ? cal.formatDate(event.end, timeFmt) : "";
          timeStr = dateStr + (startTime ? ", " + startTime : "") + (endTime ? " \u2013 " + endTime : "");
        }
        document.getElementById("eventDetailTimeText").textContent = timeStr;

        const descEl = document.getElementById("eventDetailDesc");
        descEl.textContent = props.description || "";
        descEl.style.display = props.description ? "" : "none";

        bootstrap.Modal.getOrCreateInstance(document.getElementById("eventDetailModal")).show();
      },
    });

    applyFcLocale(window.CRM.fullcalendar).then(() => {
      window.CRM.fullcalendar.render();
    });
  }

  if (typeof window.CRM.onLocalesReady === "function") {
    window.CRM.onLocalesReady(initCalendar);
  } else {
    initCalendar();
  }
});
