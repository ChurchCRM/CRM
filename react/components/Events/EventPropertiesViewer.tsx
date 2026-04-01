import * as React from "react";
import type Calendar from "../../interfaces/Calendar";
import type CRMEvent from "../../interfaces/CRMEvent";
import type EventType from "../../interfaces/EventType";

const formatDate = (date: Date | undefined, allDay: boolean): string => {
  if (!date) return window.i18next.t("N/A");
  if (allDay) return date.toLocaleDateString(undefined, { year: "numeric", month: "long", day: "numeric" });
  return date.toLocaleString(undefined, { year: "numeric", month: "long", day: "numeric", hour: "2-digit", minute: "2-digit" });
};

const EventPropertiesViewer: React.FunctionComponent<{
  event: CRMEvent;
  calendars: Array<Calendar>;
  eventTypes: Array<EventType>;
}> = ({ event, calendars, eventTypes }) => {
  const isAllDay =
    event.Start instanceof Date &&
    event.Start.getHours() === 0 &&
    event.Start.getMinutes() === 0 &&
    (!event.End || (event.End.getHours() === 0 && event.End.getMinutes() === 0));

  const matchedType = eventTypes.find((et) => event.Type != null && event.Type === et.Id);
  const pinnedCals = calendars.filter((c) => event.PinnedCalendars?.includes(c.Id));

  return (
    <div>
      {/* Dates row */}
      <div className="row g-3 mb-4">
        <div className="col-md-6">
          <div className="card card-sm">
            <div className="card-body">
              <div className="row align-items-center">
                <div className="col-auto">
                  <span className="avatar bg-blue-lt text-blue">
                    <i className="fa-regular fa-calendar-check" />
                  </span>
                </div>
                <div className="col">
                  <div className="text-muted small">{window.i18next.t("Start Date")}</div>
                  <div className="fw-medium">{formatDate(event.Start, isAllDay)}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="col-md-6">
          <div className="card card-sm">
            <div className="card-body">
              <div className="row align-items-center">
                <div className="col-auto">
                  <span className="avatar bg-red-lt text-red">
                    <i className="fa-regular fa-calendar-xmark" />
                  </span>
                </div>
                <div className="col">
                  <div className="text-muted small">{window.i18next.t("End Date")}</div>
                  <div className="fw-medium">{formatDate(event.End, isAllDay)}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Meta: Type + Calendars */}
      <dl className="row mb-3">
        {matchedType && (
          <>
            <dt className="col-sm-3 text-muted">{window.i18next.t("Event Type")}</dt>
            <dd className="col-sm-9">
              <span className="badge bg-blue-lt text-blue">{matchedType.Name}</span>
            </dd>
          </>
        )}
        {isAllDay && (
          <>
            <dt className="col-sm-3 text-muted">{window.i18next.t("Duration")}</dt>
            <dd className="col-sm-9">
              <span className="badge bg-green-lt text-green">{window.i18next.t("All Day")}</span>
            </dd>
          </>
        )}
        {pinnedCals.length > 0 && (
          <>
            <dt className="col-sm-3 text-muted">{window.i18next.t("Calendars")}</dt>
            <dd className="col-sm-9">
              <div className="d-flex flex-wrap gap-2">
                {pinnedCals.map((calendar) => (
                  <span
                    key={calendar.Id}
                    className="badge border"
                    style={{
                      backgroundColor: `#${calendar.BackgroundColor}`,
                      color: `#${calendar.ForegroundColor}`,
                      borderColor: `#${calendar.BackgroundColor}`,
                    }}
                  >
                    <span
                      className="d-inline-block rounded-circle me-1"
                      style={{
                        width: "8px",
                        height: "8px",
                        backgroundColor: `#${calendar.ForegroundColor}`,
                        opacity: 0.7,
                      }}
                    />
                    {calendar.Name}
                  </span>
                ))}
              </div>
            </dd>
          </>
        )}
      </dl>

      {/* Description */}
      {event.Desc && event.Desc.replace(/<[^>]*>/g, "").trim() && (
        <div className="mb-4">
          <h4 className="subheader">{window.i18next.t("Description")}</h4>
          {/* biome-ignore lint/security/noDangerouslySetInnerHtml: Event description is sanitized HTML from database (InputUtils::sanitizeHTML) */}
          <div className="prose" dangerouslySetInnerHTML={{ __html: event.Desc }} />
        </div>
      )}

      {/* Additional Information */}
      {event.Text && event.Text.replace(/<[^>]*>/g, "").trim() && (
        <div className="mb-2">
          <h4 className="subheader">{window.i18next.t("Additional Information")}</h4>
          {/* biome-ignore lint/security/noDangerouslySetInnerHtml: Event text is sanitized HTML from database (InputUtils::sanitizeHTML) */}
          <div className="prose" dangerouslySetInnerHTML={{ __html: event.Text }} />
        </div>
      )}
    </div>
  );
};
export default EventPropertiesViewer;
