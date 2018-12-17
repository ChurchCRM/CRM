import * as React from 'react';
import CRMEvent from '../interfaces/CRMEvent';
import { Modal, FormControl } from 'react-bootstrap';
import Select from 'react-select';


const EventPropertiesViewer: React.FunctionComponent<{ event: CRMEvent }> = ({ event }) => (
  
  
      <table>
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
            unk
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
)
export default EventPropertiesViewer;