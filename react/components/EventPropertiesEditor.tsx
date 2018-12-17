import * as React from 'react';
import CRMEvent from '../interfaces/CRMEvent';
import { Modal, FormControl } from 'react-bootstrap';
import Select from 'react-select';


const EventPropertiesEditor: React.FunctionComponent<{ event: CRMEvent }> = ({ event }) => (
<div>
    <Modal show={true} onHide={function () { }} >
      <Modal.Header>
        <input name="Title" value={event.Title} onChange={this.handleInputChange} />
      </Modal.Header>
      <Modal.Body>
        <table>
          <tr>
            <td>
              Event Type
              </td>
            <td>
              <input name="Type" value={event.Type.toString()} onChange={this.handleInputChange} />
            </td>
          </tr>
          <tr>
            <td>
              Event Description
              </td>
            <td>
              <input name="Desc" value={event.Desc} onChange={this.handleInputChange} />
            </td>
          </tr>
          <tr>
            <td>
              Date Range
              </td>
            <td>
              <input name="Start" value={event.Start} onChange={this.handleInputChange} />
              <input name="End" value={event.End} onChange={this.handleInputChange} />
            </td>
          </tr>
          <tr>
            <td>
              Pinned Calendars
              </td>
            <td>
              <Select name="PinnedCalendars"  onChange={this.updatePinnedCalendar}   />
              </td>
          </tr>
          <tr>
            <td>
              Text
              </td>
            <td>
              <input name="Text" value={event.Text} onChange={this.handleInputChange} />
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


export default EventPropertiesEditor