import * as React from 'react';
import CRMEvent from '../interfaces/CRMEvent';
import PinnedCalendar from '../interfaces/PinnedCalendar';
import { Modal, FormControl } from 'react-bootstrap';
import CRMRoot from '../window-context-service.jsx';
import EventPropertiesViewer from './EventPropertiesViewer';
import EventPropertiesEditor from './EventPropertiesEditor';
import Select from 'react-select';

class ExistingEvent extends React.Component<EventFormProps, EventFormState> {
  constructor(props: EventFormProps) {
    super(props);
    this.state = {
      isEditMode: false
    };

    this.setEditMode = this.setEditMode.bind(this);
    this.setReadOnlyMode = this.setReadOnlyMode.bind(this);
    this.handleInputChange = this.handleInputChange.bind(this);
    this.updatePinnedCalendar = this.updatePinnedCalendar.bind(this);
    this.exit = this.props.onClose.bind(this);
    this.save = this.save.bind(this);
  }

  componentDidMount() {
    // when the component monts to the DOM, then we should execut an XHR query to find the details for the supplied event id.
    fetch(CRMRoot + "/api/events/" + this.props.eventId, {
      credentials: "include"
    })
      .then(response => response.json())
      .then(data => {
        this.setState({ event: data })
      });
    this.setState({pinnedCalendars: [{ calendarId: 0, calendarName: "tesT"} as PinnedCalendar]});
  }

  setEditMode() {
    this.setState({ isEditMode: true })
  }

  setReadOnlyMode() {
    this.setState({ isEditMode: false })
  }

  handleInputChange(event: React.ChangeEvent<HTMLInputElement>) {
    console.log(event);
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    this.setState({
      event: Object.assign({}, this.state.event, { [name]: value })
    });
    console.log(this.state);
  }

  updatePinnedCalendar(event) {
    const newValue = event.label;
    this.setState({
      event: Object.assign({}, this.state.event, { PinnedCalendars: [newValue] })
    });
    console.log(this.state);
  }

  exit() {
    this.props.onClose()
  }

  save() {
    fetch(CRMRoot + "/api/events/" + this.props.eventId, {
      credentials: "include",
      method: "POST",
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(this.state.event)
    })
      .then(() => this.exit());
  }

  render() {
    if (this.state.event === null || this.state.event === undefined) {
      return (
        <div>
          <Modal show={true} onHide={function () { }} >
            <Modal.Header>
              <h2>Loading...</h2>
            </Modal.Header>
          </Modal>
        </div>
      )
    }
    else {
      
    }
    if (this.state.isEditMode) {
      return ( <div>
    <Modal show={true} onHide={function () { }} >
      <Modal.Header>
        <h2>{this.state.event.Title}</h2>
      </Modal.Header>
      <Modal.Body>
        <EventPropertiesEditor event={this.state.event} pinnedCalendars={this.state.pinnedCalendars} changeHandler={this.handleInputChange} />
      </Modal.Body>
      <Modal.Footer>
        <button className="btn btn-success" onClick={this.setEditMode}>Edit</button>
        <button className="btn btn-danger pull-left" onClick={this.setEditMode}>Delete</button>
        <button className="btn btn-default pull-right" onClick={this.exit}>Cancel</button>
      </Modal.Footer>
    </Modal>
  </div>)
      
    }
    else {
      return ( <div>
        <Modal show={true} onHide={function () { }} >
          <Modal.Header>
            <h2>{this.state.event.Title}</h2>
          </Modal.Header>
          <Modal.Body>
          <EventPropertiesViewer event={this.state.event}/>
          </Modal.Body>
          <Modal.Footer>
            <button className="btn btn-success" onClick={this.setEditMode}>Edit</button>
            <button className="btn btn-danger pull-left" onClick={this.setEditMode}>Delete</button>
            <button className="btn btn-default pull-right" onClick={this.exit}>Cancel</button>
          </Modal.Footer>
        </Modal>
      </div>
      )
    }
  }
}

interface EventFormProps {
  eventId: Number;
  onClose: Function;
}

interface EventFormState {
  event?: CRMEvent,
  isEditMode: boolean,
  pinnedCalendars?: Array<PinnedCalendar>
}

export default ExistingEvent