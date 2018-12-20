import * as React from 'react';
import CRMEvent from '../interfaces/CRMEvent';
import Calendar from '../interfaces/Calendar';

const EventPropertiesViewer: React.FunctionComponent<{ event: CRMEvent, calendars: Array<Calendar> }> = ({ event, calendars }) => { 
  return (
      <table className="table modal-table">
        <tbody>
        <tr>
          <td>
            i18next.t('Event Type')
            </td>
          <td>
            {event.Type}
          </td>
        </tr>
        <tr>
          <td>
            Event Description
            </td>
          <td>
            {event.Desc}
          </td>
        </tr>
        <tr>
          <td>
            Date Range
            </td>
          <td>
            {event.Start} {event.End}
          </td>
        </tr>
        <tr>
          <td>
            Pinned Calendars
          </td>
          <td>
            <ul>
            {
              calendars.map(
                (calendar: Calendar)=> {
                  if (event.PinnedCalendars != null && event.PinnedCalendars.includes(calendar.Id)) {
                    return (<li>{calendar.Name}</li>)
                  }
                }
              )
            }
            </ul>
          </td>
        </tr>
        <tr>
          <td>
            Text
            </td>
          <td>
            {event.Text}
          </td>
        </tr>
        </tbody>
      </table>
)}
export default EventPropertiesViewer;