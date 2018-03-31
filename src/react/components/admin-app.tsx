import * as React from 'react';
import { Modal, FormControl } from 'react-bootstrap';

class EventModal extends React.Component<EventFormProps, EventFormState> {
  constructor(props: EventFormProps) {
    super(props);
    this.state = {
      isEditMode: false
    };

    this.setEditMode = this.setEditMode.bind(this);
    this.setReadOnlyMode = this.setReadOnlyMode.bind(this);
    this.handleInputChange = this.handleInputChange.bind(this);
    this.exit = this.props.onClose.bind(this);
  }

  componentDidMount() {
    fetch("/api/events/"+this.props.eventId, {
        credentials: "include"
      })
      .then(response => response.json())
      .then(data => {
        const thisEvent = data.Events[0];
        this.setState ({ event: { 
            desc: thisEvent.Desc,
            end: thisEvent.End,
            id: thisEvent.Id,
            inActive: thisEvent.InActive,
            start: thisEvent.Start,
            text: thisEvent.Text,
            title: thisEvent.Title,
            type: thisEvent.Type
          }})
        }
      );
  }

  setEditMode() {
    this.setState({ isEditMode: true })
  }

  setReadOnlyMode() {
    this.setState({ isEditMode: false })
  }

  handleInputChange(event) {
    
    const target = event.target;
    const value = target.type === 'checkbox' ? target.checked : target.value;
    const name = target.name;

    this.setState({
      event: Object.assign({}, this.state.event, {[name]: value})
    });
    console.log(this.state);
  }

  exit() {
    this.props.onClose()
  }

  renderDisplayForm() {
    return (
      <div>
        <Modal show={true} onHide={function () { }} >
          <Modal.Header>
            <h2>{this.state.event.title}</h2>
          </Modal.Header>
          <Modal.Body>
            <table>
              <tr>
                <td>
                  Event Type
                </td>
                <td>
                  {this.state.event.type}
                </td>
              </tr>
              <tr>
                <td>
                 Event Description
                </td>
                <td>
                  {this.state.event.desc}
                </td>
              </tr>
              <tr>
                <td>
                  Date Range
                </td>
                <td>
                  {this.state.event.start} {this.state.event.end}
                </td>
              </tr>
              <tr>
                <td>
                  Pinned Calendars
                </td>
                <td>
                  unk
                </td>
              </tr>
              <tr>
                <td>
                  Text
                </td>
                <td>
                  {this.state.event.text}
                </td>
              </tr>
            </table>

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

  renderEditForm() {
    return (
      <div>
        <Modal show={true} onHide={function () { }} >
          <Modal.Header>
            <input name="title" value={this.state.event.title} onChange={this.handleInputChange} />
          </Modal.Header>
          <Modal.Body>
            <table>
              <tr>
                <td>
                  Event Type
                </td>
                <td>
                  <input name="type" value={this.state.event.type.toString()} onChange={this.handleInputChange}/>
                </td>
              </tr>
              <tr>
                <td>
                 Event Description
                </td>
                <td>
                  <input name="desc" value={this.state.event.desc} onChange={this.handleInputChange}/>
                </td>
              </tr>
              <tr>
                <td>
                  Date Range
                </td>
                <td>
                  <input name="start" value={this.state.event.start} onChange={this.handleInputChange}/>
                  <input name="end" value={this.state.event.end} onChange={this.handleInputChange}/>
                </td>
              </tr>
              <tr>
                <td>
                  Pinned Calendars
                </td>
                <td>
                  unk
                </td>
              </tr>
              <tr>
                <td>
                  Text
                </td>
                <td>
                  <input name="text" value={this.state.event.text} onChange={this.handleInputChange}/>
                </td>
              </tr>
            </table>

          </Modal.Body>
          <Modal.Footer>
            <button className="btn btn-success" onClick={this.setReadOnlyMode}>Read Only</button>
            <button className="btn btn-danger pull-left" onClick={this.setEditMode}>Delete</button>
            <button className="btn btn-default pull-right" onClick={this.exit}>Cancel</button>
          </Modal.Footer>
        </Modal>
      </div>
    )
  }

  render() {
    if (this.state.event === null || this.state.event === undefined)
    {
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
    if (this.state.isEditMode) {
      return this.renderEditForm();
    }
    else {
      return this.renderDisplayForm();
    }
  }
}


export default class App extends React.Component<{unmountCall:Function, eventId: number}> {

  render() {
    return (
      <div >
        <EventModal onClose={this.props.unmountCall} eventId={this.props.eventId} />
      </div>
    );
  }
};


interface CRMEvent {
  desc: string,
  end: string,
  id: number,
  inActive: boolean,
  start: string,
  text: string,
  type: number,
  title: string
}

interface EventFormProps {
  eventId: Number;
  onClose: Function;
}

interface EventFormState {
  event?: CRMEvent,
  isEditMode: boolean
}