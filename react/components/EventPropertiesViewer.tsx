import * as React from 'react';
import CRMEvent from '../interfaces/CRMEvent';
import Calendar from '../interfaces/Calendar';
import EventType from '../interfaces/EventType';
import Moment from 'react-moment';

const EventPropertiesViewer: React.FunctionComponent<{ event: CRMEvent, calendars: Array<Calendar>, eventTypes: Array<EventType> }> = ({ event, calendars, eventTypes }) => { 
  return (
      <table className="table modal-table">
        <tbody>
        <tr>
          <td>
            i18next.t('Event Type')
            </td>
          <td>
          {
              eventTypes.map(
                (eventType: EventType)=> {
                  if (event.Type != null && event.Type == eventType.Id) {
                    return (<p>{eventType.Name}</p>)
                  }
                }
              )
            }
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
          <Moment format="MMM Do YYYY, h:mm:ss a">{event.Start}</Moment> - 
          <Moment format="MMM Do YYYY, h:mm:ss a">{event.End}</Moment>
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