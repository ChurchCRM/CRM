import * as React from "react";
import CRMEvent from "../../interfaces/CRMEvent";
import Calendar from "../../interfaces/Calendar";
import EventType from "../../interfaces/EventType";
import { Modal } from "react-bootstrap";
import CRMRoot from "../../window-context-service.jsx";
import EventPropertiesViewer from "./EventPropertiesViewer";
import EventPropertiesEditor from "./EventPropertiesEditor";
import { SingleValue, MultiValue } from "react-select";

interface Option {
  value: number;
  label: string;
}

interface EventFormProps {
  eventId: number;
  onClose: () => void;
  start?: Date;
  end?: Date;
}

interface EventFormState {
  event?: CRMEvent;
  isEditMode: boolean;
  calendars: Array<Calendar>;
  eventTypes: Array<EventType>;
}

class ExistingEvent extends React.Component<EventFormProps, EventFormState> {
  constructor(props: EventFormProps) {
    super(props);

    this.state = {
      isEditMode: false,
      calendars: [],
      eventTypes: [],
    };
    if (this.props.eventId === 0) {
      this.state = {
        isEditMode: true,
        calendars: [],
        eventTypes: [],
        event: {
          Id: 0,
          Title: "",
          Type: 0,
          PinnedCalendars: [],
        },
      };
      if (this.props.start && this.state.event) {
        this.state.event.Start = this.props.start;
      }
      if (this.props.end && this.state.event) {
        this.state.event.End = this.props.end;
      }
    }

    this.setEditMode = this.setEditMode.bind(this);
    this.setReadOnlyMode = this.setReadOnlyMode.bind(this);
    this.handleInputChange = this.handleInputChange.bind(this);
    this.updatePinnedCalendar = this.updatePinnedCalendar.bind(this);
    this.handleStartDateChange = this.handleStartDateChange.bind(this);
    this.handleEndDateChange = this.handleEndDateChange.bind(this);
    this.updateEventType = this.updateEventType.bind(this);
    this.delete = this.delete.bind(this);
    this.exit = this.props.onClose.bind(this);
    this.save = this.save.bind(this);
  }

  componentDidMount() {
    if (this.props.eventId !== 0) {
      // when the component mounts to the DOM, then we should execute an XHR query to find the details for the supplied event id.
      fetch(CRMRoot + "/api/events/" + this.props.eventId, {
        credentials: "include",
      })
        .then((response) => response.json())
        .then((data) => {
          const event = data as CRMEvent;
          if (event.Start) {
            event.Start = new Date(event.Start);
          }
          if (event.End) {
            event.End = new Date(event.End);
          }
          this.setState({ event: event });
        });
    }

    fetch(CRMRoot + "/api/calendars", {
      credentials: "include",
    })
      .then((response) => response.json())
      .then((data) => {
        this.setState({ calendars: data.Calendars });
      });

    fetch(CRMRoot + "/api/events/types", {
      credentials: "include",
    })
      .then((response) => response.json())
      .then((data) => {
        this.setState({ eventTypes: data.EventTypes });
      });
  }

  setEditMode() {
    this.setState({ isEditMode: true });
  }

  setReadOnlyMode() {
    this.setState({ isEditMode: false });
  }

  handleInputChange(event: React.ChangeEvent<HTMLInputElement>) {
    const target = event.target;
    const value = target.type === "checkbox" ? target.checked : target.value;
    const name = target.name;

    this.setState({
      event: Object.assign({}, this.state.event, { [name]: value }),
    });
  }

  handleStartDateChange(date: Date | null) {
    if (date) {
      const newEventState = Object.assign({}, this.state.event, { Start: date });
      this.setState({
        event: newEventState,
      });
    }
  }

  handleEndDateChange(date: Date | null) {
    if (date) {
      const newEventState = Object.assign({}, this.state.event, { End: date });
      this.setState({
        event: newEventState,
      });
    }
  }

  updatePinnedCalendar(selectedOptions: MultiValue<Option>) {
    const pinnedCalendars = selectedOptions.map((selected) => selected.value);
    this.setState({
      event: Object.assign({}, this.state.event, {
        PinnedCalendars: pinnedCalendars,
      }),
    });
  }

  updateEventType(selectedOption: SingleValue<Option>) {
    if (selectedOption) {
      const eventType = selectedOption.value;
      this.setState({
        event: Object.assign({}, this.state.event, { Type: eventType }),
      });
    }
  }

  isFormComplete(): boolean {
    if (!this.state.event) return false;
    if (!this.state.event.PinnedCalendars || this.state.event.PinnedCalendars.length === 0) return false;
    if (!this.state.event.Title || this.state.event.Title.length === 0) return false;
    if (this.state.event.Start === undefined || this.state.event.Start === null) return false;
    if (this.state.event.End === undefined || this.state.event.End === null) return false;
    return true;
  }

  exit() {
    this.props.onClose();
  }

  save() {
    const DateReplacer = (key: string, value: unknown): unknown => {
      const obj = this.state.event as Record<string, unknown> | undefined;
      if (obj && obj[key] instanceof Date) {
        const td = obj[key] as Date;
        const w = window as unknown as { moment?: (d: Date) => { format(): string } };
        return w.moment ? w.moment(td).format() : td.toISOString();
      }
      return value;
    };
    if (!this.state.event) return;
    fetch(
      CRMRoot +
        "/api/events" +
        (this.state.event.Id !== 0 ? "/" + this.state.event.Id : ""),
      {
        credentials: "include",
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify(this.state.event, DateReplacer),
      },
    ).then(() => this.exit());
  }

  delete() {
    fetch(CRMRoot + "/api/events/" + this.props.eventId, {
      credentials: "include",
      method: "DELETE",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    }).then(() => this.exit());
  }

  render() {
    if (this.state.event === null || this.state.event === undefined) {
      return (
        <div>
          <Modal show={true} onHide={function () {}} size="xl">
            <Modal.Header>
              <h2>Loading...</h2>
            </Modal.Header>
          </Modal>
        </div>
      );
  }
    if (this.state.isEditMode) {
      return (
        <div>
          <Modal show={true} onHide={function () {}} size="xl">
            <Modal.Header>
              <div style={{ width: "100%" }}>
                <input
                  name="Title"
                  value={this.state.event?.Title || ""}
                  onChange={this.handleInputChange}
                  placeholder={window.i18next.t("Event Title")}
                  className="form-control form-control-lg"
                  style={{ fontSize: "1.5rem", fontWeight: "bold" }}
                />
                {this.state.event && this.state.event.Title && this.state.event.Title.length === 0 && (
                  <div className="text-danger small mt-2">
                    <i className="fas fa-exclamation-circle me-1"></i>
                    {window.i18next.t("This field is required")}
                  </div>
                )}
              </div>
            </Modal.Header>
            <Modal.Body style={{ overflow: "visible", maxHeight: "none" }}>
              <div style={{ overflow: "visible" }}>
                <EventPropertiesEditor
                  event={this.state.event}
                  calendars={this.state.calendars}
                  eventTypes={this.state.eventTypes}
                  changeHandler={this.handleInputChange}
                  handleStartDateChange={this.handleStartDateChange}
                  handleEndDateChange={this.handleEndDateChange}
                  pinnedCalendarChanged={this.updatePinnedCalendar}
                  eventTypeChanged={this.updateEventType}
                />
              </div>
            </Modal.Body>
            <Modal.Footer>
              <button
                disabled={!this.isFormComplete()}
                className="btn btn-success"
                onClick={this.save}
              >
                Save
              </button>
              <button
                className="btn btn-danger pull-left"
                onClick={this.delete}
              >
                Delete
              </button>
              <button
                className="btn btn-default pull-right"
                onClick={this.exit}
              >
                Cancel
              </button>
            </Modal.Footer>
          </Modal>
        </div>
      );
    } else {
      return (
        <div>
          <Modal show={true} onHide={function () {}} size="xl">
            <Modal.Header>
              <h2>{this.state.event.Title}</h2>
            </Modal.Header>
            <Modal.Body>
              <EventPropertiesViewer
                event={this.state.event}
                calendars={this.state.calendars}
                eventTypes={this.state.eventTypes}
              />
            </Modal.Body>
            <Modal.Footer>
              <button className="btn btn-success" onClick={this.setEditMode}>
                Edit
              </button>
              <button
                className="btn btn-danger pull-left"
                onClick={this.delete}
              >
                Delete
              </button>
              <button
                className="btn btn-default pull-right"
                onClick={this.exit}
              >
                Cancel
              </button>
            </Modal.Footer>
          </Modal>
        </div>
      );
    }
  }
}

export default ExistingEvent;
