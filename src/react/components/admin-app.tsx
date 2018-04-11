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
    this.save = this.save.bind(this);
  }

  componentDidMount() {
    fetch("/api/events/"+this.props.eventId, {
        credentials: "include"
      })
      .then(response => response.json())
      .then(data => {
        this.setState ({ event: data})
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

  save() {
    fetch("/api/events/"+this.props.eventId, {
      credentials: "include",
      method:"POST",
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(this.state.event)
    })
    .then(()=>this.exit());
  }

  renderDisplayForm() {
    return (
      <div>
        <Modal show={true} onHide={function () { }} >
          <Modal.Header>
            <h2>{this.state.event.Title}</h2>
          </Modal.Header>
          <Modal.Body>
            <table>
              <tr>
                <td>
                  Event Type
                </td>
                <td>
                  {this.state.event.Type}
                </td>
              </tr>
              <tr>
                <td>
                 Event Description
                </td>
                <td>
                  {this.state.event.Desc}
                </td>
              </tr>
              <tr>
                <td>
                  Date Range
                </td>
                <td>
                  {this.state.event.Start} {this.state.event.End}
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
                  {this.state.event.Text}
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
            <input name="Title" value={this.state.event.Title} onChange={this.handleInputChange} />
          </Modal.Header>
          <Modal.Body>
            <table>
              <tr>
                <td>
                  Event Type
                </td>
                <td>
                  <input name="Type" value={this.state.event.Type.toString()} onChange={this.handleInputChange}/>
                </td>
              </tr>
              <tr>
                <td>
                 Event Description
                </td>
                <td>
                  <input name="Desc" value={this.state.event.Desc} onChange={this.handleInputChange}/>
                </td>
              </tr>
              <tr>
                <td>
                  Date Range
                </td>
                <td>
                  <input name="Start" value={this.state.event.Start} onChange={this.handleInputChange}/>
                  <input name="End" value={this.state.event.End} onChange={this.handleInputChange}/>
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
                  <input name="Text" value={this.state.event.Text} onChange={this.handleInputChange}/>
                </td>
              </tr>
            </table>

          </Modal.Body>
          <Modal.Footer>
            <button className="btn btn-success" onClick={this.save}>Save</button>
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
  Desc: string,
  End: string,
  Id: number,
  InActive: boolean,
  Start: string,
  Text: string,
  Type: number,
  Title: string
}

interface EventFormProps {
  eventId: Number;
  onClose: Function;
}

interface EventFormState {
  event?: CRMEvent,
  isEditMode: boolean
}