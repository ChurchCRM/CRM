import * as React from "react";
import CRMEvent from "../../interfaces/CRMEvent";
import Calendar from "../../interfaces/Calendar";
import EventType from "../../interfaces/EventType";
import { Modal } from "react-bootstrap";
import CRMRoot from "../../window-context-service.jsx";
import EventPropertiesViewer from "./EventPropertiesViewer";
import EventPropertiesEditor from "./EventPropertiesEditor";

class ExistingEvent extends React.Component<EventFormProps, EventFormState> {
  constructor(props: EventFormProps) {
    super(props);

    this.state = {
      isEditMode: false,
      calendars: [],
      eventTypes: [],
    };
    if (this.props.eventId == 0) {
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
      if (this.props.start) {
        this.state.event.Start = this.props.start;
      }
      if (this.props.end) {
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
    if (this.props.eventId != 0) {
      // when the component mounts to the DOM, then we should execute an XHR query to find the details for the supplied event id.
      fetch(CRMRoot + "/api/events/" + this.props.eventId, {
        credentials: "include",
      })
        .then((response) => response.json())
        .then((data) => {
          var event: CRMEvent;
          event = data;
          event.Start = new Date(event.Start);
          event.End = new Date(event.End);
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

  handleStartDateChange(date: Date) {
    var newEventState = Object.assign({}, this.state.event, { Start: date });
    this.setState({
      event: newEventState,
    });
  }

  handleEndDateChange(date: Date) {
    var newEventState = Object.assign({}, this.state.event, { End: date });
    this.setState({
      event: newEventState,
    });
  }

  updatePinnedCalendar(event) {
    const pinnedCalendars = event.map(
      (selected: { value: number; label: string }) => selected.value,
    );
    this.setState({
      event: Object.assign({}, this.state.event, {
        PinnedCalendars: pinnedCalendars,
      }),
    });
  }

  updateEventType(event) {
    const eventType = event.value;
    this.setState({
      event: Object.assign({}, this.state.event, { Type: eventType }),
    });
  }

  isFormComplete(): boolean {
    return (
      this.state.event.PinnedCalendars.length > 0 &&
      this.state.event.Title.length > 0 &&
      this.state.event.Start != null &&
      this.state.event.End != null
    );
  }

  exit() {
    this.props.onClose();
  }

  save() {
    var DateReplacer = function (key, value) {
      if (this[key] instanceof Date) {
        var td = this[key];
        return window.moment(td).format();
      }

      return value;
    };
    fetch(
      CRMRoot +
        "/api/events" +
        (this.state.event.Id != 0 ? "/" + this.state.event.Id : ""),
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
          <Modal show={true} onHide={function () {}}>
            <Modal.Header>
              <h2>Loading...</h2>
            </Modal.Header>
          </Modal>
        </div>
      );
    } else {
    }
    if (this.state.isEditMode) {
      return (
        <div>
          <Modal show={true} onHide={function () {}}>
            <Modal.Header>
              <input
                name="Title"
                value={this.state.event.Title}
                onChange={this.handleInputChange}
                placeholder={window.i18next.t("Event Title")}
              />
              <span
                className={
                  this.state.event.Title.length == 0
                    ? "RequiredFormFieldUnsatisfied"
                    : "RequiredFormFieldSatisfied"
                }
              >
                {window.i18next.t("This field is required")}
              </span>
            </Modal.Header>
            <Modal.Body>
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
          <Modal show={true} onHide={function () {}}>
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

interface EventFormProps {
  eventId: Number;
  onClose: Function;
  start?: Date;
  end?: Date;
}

interface EventFormState {
  event?: CRMEvent;
  isEditMode: boolean;
  calendars: Array<Calendar>;
  eventTypes: Array<EventType>;
}

export default ExistingEvent;
