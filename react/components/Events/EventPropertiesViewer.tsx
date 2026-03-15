import type * as React from "react";
import type Calendar from "../../interfaces/Calendar";
import type CRMEvent from "../../interfaces/CRMEvent";
import type EventType from "../../interfaces/EventType";

const EventPropertiesViewer: React.FunctionComponent<{
  event: CRMEvent;
  calendars: Array<Calendar>;
  eventTypes: Array<EventType>;
}> = ({ event, calendars, eventTypes }) => {
  return (
    <table className="table w-100" style={{ tableLayout: "fixed" }}>
      <tbody>
        <tr>
          <td>{window.i18next.t("Type")}</td>
          <td>
            {eventTypes.map((eventType: EventType) => {
              if (event.Type != null && event.Type === eventType.Id) {
                return <p key={eventType.Id}>{eventType.Name}</p>;
              }
              return null;
            })}
          </td>
        </tr>
        <tr>
          <td>{window.i18next.t("Event Description")}</td>
          {/* biome-ignore lint/security/noDangerouslySetInnerHtml: Event description is sanitized HTML from database (InputUtils::sanitizeHTML) */}
          <td dangerouslySetInnerHTML={{ __html: event.Desc || "" }} />
        </tr>
        <tr>
          <td>{window.i18next.t("Start Date")}</td>
          <td>{event.Start ? event.Start.toString() : "N/A"}</td>
        </tr>
        <tr>
          <td>{window.i18next.t("End Date")}</td>
          <td>{event.End ? event.End.toString() : "N/A"}</td>
        </tr>
        <tr>
          <td>{window.i18next.t("Pinned Calendars")}</td>
          <td>
            <ul>
              {calendars.map((calendar: Calendar) => {
                if (event.PinnedCalendars?.includes(calendar.Id)) {
                  return <li key={calendar.Id}>{calendar.Name}</li>;
                }
                return null;
              })}
            </ul>
          </td>
        </tr>
        <tr>
          <td>{window.i18next.t("Text")}</td>
          {/* biome-ignore lint/security/noDangerouslySetInnerHtml: Event text is sanitized HTML from database (InputUtils::sanitizeHTML) */}
          <td dangerouslySetInnerHTML={{ __html: event.Text || "" }} />
        </tr>
      </tbody>
    </table>
  );
};
export default EventPropertiesViewer;
