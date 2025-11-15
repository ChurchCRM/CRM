import * as React from "react";
import CRMEvent from "../../interfaces/CRMEvent";
import Calendar from "../../interfaces/Calendar";
import EventType from "../../interfaces/EventType";

const EventPropertiesViewer: React.FunctionComponent<{
  event: CRMEvent;
  calendars: Array<Calendar>;
  eventTypes: Array<EventType>;
}> = ({ event, calendars, eventTypes }) => {
  return (
    <table className="table w-100" style={{ tableLayout: 'fixed' }}>
      <tbody>
        <tr>
          <td>{window.i18next.t("Type")}</td>
          <td>
            {eventTypes.map((eventType: EventType) => {
              if (event.Type != null && event.Type == eventType.Id) {
                return <p key={eventType.Id}>{eventType.Name}</p>;
              }
            })}
          </td>
        </tr>
        <tr>
          <td>{window.i18next.t("Event Description")}</td>
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
                if (
                  event.PinnedCalendars != null &&
                  event.PinnedCalendars.includes(calendar.Id)
                ) {
                  return <li key={calendar.Id}>{calendar.Name}</li>;
                }
              })}
            </ul>
          </td>
        </tr>
        <tr>
          <td>{window.i18next.t("Text")}</td>
          <td dangerouslySetInnerHTML={{ __html: event.Text || "" }} />
        </tr>
      </tbody>
    </table>
  );
};
export default EventPropertiesViewer;
