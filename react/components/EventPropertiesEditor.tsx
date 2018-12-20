import * as React from 'react';
import CRMEvent from '../interfaces/CRMEvent';
import Calendar from '../interfaces/Calendar';
import { Modal, FormControl } from 'react-bootstrap';
import Select from 'react-select';


const EventPropertiesEditor: React.FunctionComponent<{ event: CRMEvent, calendars: Array<Calendar>, changeHandler: (event:React.ChangeEvent)=>void, pinnedCalendarChanged: (event: Array<Object>) => void }> = ({ event, calendars, changeHandler, pinnedCalendarChanged }) => {
  //map the Calendar data type (returned from CRM API) into something that react-select can present as dropdown choices
  var options=calendars.map((Pcal:Calendar) => ({value: Pcal.Id,  label: Pcal.Name}) );
  var initialValue=calendars.map((Pcal:Calendar) => {if (event.PinnedCalendars.includes(Pcal.Id) ) { return {value: Pcal.Id,  label: Pcal.Name}} } );
  return (
    <table className="table modal-table">
      <tbody>
        <tr>
          <td className="LabelColumn">
            Event Type
            </td>
          <td className="TextColumn">
            <input name="Type" value={event.Type.toString()} onChange={changeHandler} />
          </td>
        </tr>
        <tr>
         <td className="LabelColumn">
            Event Description
            </td>
          <td className="TextColumn">
            <textarea name="Desc" value={event.Desc} onChange={changeHandler} />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">
            Date Range
            </td>
          <td className="TextColumn">
            <input name="Start" value={event.Start} onChange={changeHandler} />
            <input name="End" value={event.End} onChange={changeHandler} />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">
            Pinned Calendars
            </td>
          <td className="TextColumn">
            <Select name="PinnedCalendars" options={options} value={initialValue} onChange={pinnedCalendarChanged} isMulti="true"  />
            </td>
        </tr>
        <tr>
          <td className="LabelColumn">
            Text
            </td>
          <td className="TextColumn">
            <textarea name="Text" value={event.Text} onChange={changeHandler} />
          </td>
        </tr>
      </tbody>
    </table>
)}


export default EventPropertiesEditor