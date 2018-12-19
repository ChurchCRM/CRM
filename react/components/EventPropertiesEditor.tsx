import * as React from 'react';
import CRMEvent from '../interfaces/CRMEvent';
import Calendar from '../interfaces/Calendar';
import { Modal, FormControl } from 'react-bootstrap';
import Select from 'react-select';


const EventPropertiesEditor: React.FunctionComponent<{ event: CRMEvent, calendars: Array<Calendar>, changeHandler: (event:React.ChangeEvent<HTMLInputElement>)=>void, pinnedCalendarChanged: (event: Array<Object>) => void }> = ({ event, calendars, changeHandler, pinnedCalendarChanged }) => {
  //map the Calendar data type (returned from CRM API) into something that react-select can present as dropdown choices
  var options=calendars.map((Pcal:Calendar) => ({value: Pcal.Id,  label: Pcal.Name}) );
  var initialValue=calendars.map((Pcal:Calendar) => {if (event.PinnedCalendars.includes(Pcal.Id) ) { return {value: Pcal.Id,  label: Pcal.Name}} } );
  return (
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
            <Select name="PinnedCalendars" options={options} value={initialValue} onChange={pinnedCalendarChanged} isMulti="true"  />
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
)}


export default EventPropertiesEditor