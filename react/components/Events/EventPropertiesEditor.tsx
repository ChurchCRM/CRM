import * as React from "react";
import DatePicker from "react-datepicker";
import Select, { type ActionMeta, type MultiValue, type SingleValue } from "react-select";
import type Calendar from "../../interfaces/Calendar";
import type CRMEvent from "../../interfaces/CRMEvent";
import type EventType from "../../interfaces/EventType";
import QuillEditor from "../QuillEditor";

interface Option {
  value: number;
  label: string;
}

const EventPropertiesEditor: React.FunctionComponent<{
  event: CRMEvent;
  calendars: Array<Calendar>;
  eventTypes: Array<EventType>;
  changeHandler: (event: React.ChangeEvent<HTMLInputElement>) => void;
  handleStartDateChange: (date: Date | null) => void;
  handleEndDateChange: (date: Date | null) => void;
  pinnedCalendarChanged: (newValue: MultiValue<Option>, actionMeta: ActionMeta<Option>) => void;
  eventTypeChanged: (newValue: SingleValue<Option>, actionMeta: ActionMeta<Option>) => void;
}> = ({
  event,
  calendars,
  eventTypes,
  changeHandler,
  handleStartDateChange,
  handleEndDateChange,
  pinnedCalendarChanged,
  eventTypeChanged,
}) => {
  const [allDay, setAllDay] = React.useState<boolean>(!event.Start || (event.Start.getHours() === 0 && event.Start.getMinutes() === 0 && event.End?.getHours() === 0 && event.End?.getMinutes() === 0));

  const calendarOptions: Option[] = calendars.map((Pcal: Calendar) => ({
    value: Pcal.Id,
    label: Pcal.Name,
  }));
  const EventTypeOptions: Option[] = eventTypes.map((eventType: EventType) => ({
    value: eventType.Id,
    label: eventType.Name,
  }));
  const initialPinnedCalendarValue: Option[] = calendars
    .filter((Pcal: Calendar) => event.PinnedCalendars?.includes(Pcal.Id))
    .map((Pcal: Calendar) => ({ value: Pcal.Id, label: Pcal.Name }));
  const initialEventTypeValue: Option | undefined = eventTypes
    .map((eventType: EventType) => {
      if (event.Type === eventType.Id) {
        return { value: eventType.Id, label: eventType.Name };
      }
      return undefined;
    })
    .find((option) => option !== undefined);

  const handleAllDayToggle = (e: React.ChangeEvent<HTMLInputElement>) => {
    const checked = e.target.checked;
    setAllDay(checked);
    if (checked) {
      // Strip time — set both to midnight
      const start = event.Start ? new Date(event.Start) : new Date();
      start.setHours(0, 0, 0, 0);
      handleStartDateChange(start);
      const end = event.End ? new Date(event.End) : new Date(start);
      end.setHours(0, 0, 0, 0);
      handleEndDateChange(end);
    } else {
      // Restore a sensible default time (current hour, on the hour)
      const now = new Date();
      const start = event.Start ? new Date(event.Start) : new Date();
      start.setHours(now.getHours(), 0, 0, 0);
      handleStartDateChange(start);
      const end = event.End ? new Date(event.End) : new Date(start);
      end.setHours(now.getHours() + 1, 0, 0, 0);
      handleEndDateChange(end);
    }
  };

  const pinnedCalendarsInvalid = event.PinnedCalendars !== undefined && event.PinnedCalendars.length === 0;

  return (
    <div>
      {/* Row 1: Event Type + Pinned Calendars */}
      <div className="row g-3 mb-3">
        <div className="col-md-6">
          <label className="form-label" htmlFor="EventType">
            {window.i18next.t("Event Type")}
          </label>
          <Select
            name="EventType"
            inputId="EventType"
            options={EventTypeOptions}
            value={initialEventTypeValue ?? null}
            onChange={eventTypeChanged}
            placeholder={window.i18next.t("Select event type...")}
            classNamePrefix="react-select"
          />
        </div>
        <div className="col-md-6">
          <label className="form-label" htmlFor="PinnedCalendars">
            {window.i18next.t("Pinned Calendars")}
            <span className="text-danger ms-1">*</span>
          </label>
          <Select
            name="PinnedCalendars"
            inputId="PinnedCalendars"
            options={calendarOptions}
            value={initialPinnedCalendarValue}
            onChange={pinnedCalendarChanged}
            isMulti={true}
            placeholder={window.i18next.t("Select calendars...")}
            classNamePrefix="react-select"
          />
          {pinnedCalendarsInvalid && (
            <div className="invalid-feedback d-block">
              <i className="fas fa-exclamation-circle me-1" />
              {window.i18next.t("This field is required")}
            </div>
          )}
        </div>
      </div>

      {/* Row 2: dates + all-day */}
      <div className="row g-3 mb-3">
        <div className="col-md-6">
          <div className="d-flex align-items-center justify-content-between mb-1">
            <label className="form-label mb-0" htmlFor="StartDate">
              {window.i18next.t("Start Date")}
              <span className="text-danger ms-1">*</span>
            </label>
            <label className="form-check mb-0 ms-3" htmlFor="allDayToggle">
              <input
                className="form-check-input"
                type="checkbox"
                id="allDayToggle"
                checked={allDay}
                onChange={handleAllDayToggle}
              />
              <span className="form-check-label text-muted small">{window.i18next.t("All Day")}</span>
            </label>
          </div>
          <DatePicker
            selected={event.Start ?? null}
            onChange={handleStartDateChange}
            showTimeSelect={!allDay}
            timeIntervals={15}
            dateFormat={allDay ? "MMMM d, yyyy" : "MMMM d, yyyy h:mm aa"}
            timeCaption={window.i18next.t("Time")}
            className="form-control w-100"
            placeholderText={window.i18next.t("Start Date")}
            autoComplete="off"
            wrapperClassName="w-100"
            id="StartDate"
          />
        </div>
        <div className="col-md-6">
          <label className="form-label" htmlFor="EndDate">
            {window.i18next.t("End Date")}
            <span className="text-danger ms-1">*</span>
          </label>
          <DatePicker
            selected={event.End ?? null}
            onChange={handleEndDateChange}
            showTimeSelect={!allDay}
            timeIntervals={15}
            dateFormat={allDay ? "MMMM d, yyyy" : "MMMM d, yyyy h:mm aa"}
            timeCaption={window.i18next.t("Time")}
            className="form-control w-100"
            placeholderText={window.i18next.t("End Date")}
            autoComplete="off"
            wrapperClassName="w-100"
            minDate={event.Start ?? undefined}
            id="EndDate"
          />
        </div>
      </div>

      {/* Description */}
      <div className="mb-3">
        <label className="form-label">{window.i18next.t("Description")}</label>
        <QuillEditor
          name="Desc"
          value={event.Desc || ""}
          onChange={(name: string, html: string) => {
            const changeEvent = {
              target: { name, value: html },
            } as unknown as React.ChangeEvent<HTMLInputElement>;
            changeHandler(changeEvent);
          }}
          minHeight="150px"
        />
      </div>

      {/* Additional Text */}
      <div className="mb-3">
        <label className="form-label">{window.i18next.t("Additional Information")}</label>
        <QuillEditor
          name="Text"
          value={event.Text || ""}
          onChange={(name: string, html: string) => {
            const changeEvent = {
              target: { name, value: html },
            } as unknown as React.ChangeEvent<HTMLInputElement>;
            changeHandler(changeEvent);
          }}
          minHeight="150px"
        />
      </div>
    </div>
  );
};

export default EventPropertiesEditor;
