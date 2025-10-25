import * as React from "react";
import CRMEvent from "../../interfaces/CRMEvent";
import Calendar from "../../interfaces/Calendar";
import EventType from "../../interfaces/EventType";
import Select from "react-select";
import DatePicker from "react-datepicker";
import QuillEditor from "../QuillEditor";

const EventPropertiesEditor: React.FunctionComponent<{
  event: CRMEvent;
  calendars: Array<Calendar>;
  eventTypes: Array<EventType>;
  changeHandler: (event: React.ChangeEvent) => void;
  handleStartDateChange: (date: any) => void;
  handleEndDateChange: (date: any) => void;
  pinnedCalendarChanged: (event: Array<Object>) => void;
  eventTypeChanged: (event: Array<Object>) => void;
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
  //map the Calendar data type (returned from CRM API) into something that react-select can present as dropdown choices
  const calendarOptions = calendars.map((Pcal: Calendar) => ({
    value: Pcal.Id,
    label: Pcal.Name,
  }));
  const EventTypeOptions = eventTypes.map((eventType: EventType) => ({
    value: eventType.Id,
    label: eventType.Name,
  }));
  const initialPinnedCalendarValue = calendars
    .filter((Pcal: Calendar) => event.PinnedCalendars.includes(Pcal.Id))
    .map((Pcal: Calendar) => ({ value: Pcal.Id, label: Pcal.Name }));
  const initialEventTypeValue = eventTypes.map((eventType: EventType) => {
    if (event.Type == eventType.Id) {
      return { value: eventType.Id, label: eventType.Name };
    }
  });
  return (
    <table className="table modal-table">
      <tbody>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Event Type")}</td>
          <td className="TextColumn">
            <Select
              name="EventType"
              inputId="EventType"
              options={EventTypeOptions}
              value={initialEventTypeValue}
              onChange={eventTypeChanged}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Description")}</td>
          <td className="TextColumn">
            <QuillEditor 
              name="Desc" 
              value={event.Desc} 
              onChange={(name, html) => {
                const changeEvent = {
                  target: { name, value: html }
                } as any;
                changeHandler(changeEvent);
              }}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Start Date")}</td>
          <td className="TextColumn">
            <DatePicker
              selected={event.Start}
              onChange={handleStartDateChange}
              showTimeSelect
              timeIntervals={15}
              dateFormat="MMMM d, yyyy h:mm aa"
              timeCaption="time"
              className="form-control"
              placeholderText={window.i18next.t("Start Date")}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("End Date")}</td>
          <td className="TextColumn">
            <DatePicker
              selected={event.End}
              onChange={handleEndDateChange}
              showTimeSelect
              timeIntervals={15}
              dateFormat="MMMM d, yyyy h:mm aa"
              timeCaption="time"
              className="form-control"
              placeholderText={window.i18next.t("End Date")}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">
            <span>{window.i18next.t("Pinned Calendars")}</span>
            <span
              className={
                event.PinnedCalendars.length == 0
                  ? "RequiredFormFieldUnsatisfied"
                  : "RequiredFormFieldSatisfied"
              }
            >
              {window.i18next.t("This field is required")}
            </span>
          </td>
          <td className="TextColumn">
            <Select
              name="PinnedCalendars"
              inputId="PinnedCalendars"
              options={calendarOptions}
              value={initialPinnedCalendarValue}
              onChange={pinnedCalendarChanged}
              isMulti={true}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Text")}</td>
          <td className="TextColumn">
            <QuillEditor 
              name="Text" 
              value={event.Text} 
              onChange={(name, html) => {
                const changeEvent = {
                  target: { name, value: html }
                } as any;
                changeHandler(changeEvent);
              }}
              minHeight="300px"
            />
          </td>
        </tr>
      </tbody>
    </table>
  );
};

export default EventPropertiesEditor;
