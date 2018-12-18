import * as React from 'react';
import CRMEvent from '../interfaces/CRMEvent';
import Calendar from '../interfaces/Calendar';
import { Modal, FormControl } from 'react-bootstrap';
import Select from 'react-select';


const EventPropertiesEditor: React.FunctionComponent<{ event: CRMEvent, calendars: Array<Calendar>, changeHandler: (event:React.ChangeEvent<HTMLInputElement>)=>void }> = ({ event, calendars, changeHandler }) => {
  console.log(calendars);
  //map the Calendar data type (returned from CRM API) into something that react-select can present as dropdown choices
  var options=calendars.map((Pcal:Calendar) => ({value: Pcal.Id,  label: Pcal.Name}) );
  console.log(options);
  return (
<div>
    <Modal show={true} onHide={function () { }} >
      <Modal.Header>
        <input name="Title" value={event.Title} onChange={changeHandler} />
      </Modal.Header>
      <Modal.Body>
        <table>
          <tbody>
            <tr>
              <td>
                Event Type
                </td>
              <td>
                <input name="Type" value={event.Type.toString()} onChange={changeHandler} />
              </td>
            </tr>
            <tr>
              <td>
                Event Description
                </td>
              <td>
                <input name="Desc" value={event.Desc} onChange={changeHandler} />
              </td>
            </tr>
            <tr>
              <td>
                Date Range
                </td>
              <td>
                <input name="Start" value={event.Start} onChange={changeHandler} />
                <input name="End" value={event.End} onChange={changeHandler} />
              </td>
            </tr>
            <tr>
              <td>
                Pinned Calendars
                </td>
              <td>
                <Select name="PinnedCalendars" options={options} onChange={changeHandler}   />
                </td>
            </tr>
            <tr>
              <td>
                Text
                </td>
              <td>
                <input name="Text" value={event.Text} onChange={changeHandler} />
              </td>
            </tr>
          </tbody>
        </table>

      </Modal.Body>
      <Modal.Footer>
        <button className="btn btn-success" onClick={this.save}>Save</button>
        <button className="btn btn-danger pull-left" onClick={this.setEditMode}>Delete</button>
        <button className="btn btn-default pull-right" onClick={this.exit}>Cancel</button>
      </Modal.Footer>
    </Modal>
  </div>
)}


export default EventPropertiesEditor