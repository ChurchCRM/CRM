import * as React from 'react';
import * as ReactDOM from 'react-dom';
import CRMEvent from './interfaces/CRMEvent';
import EventModal from './components/EventModal';
declare global {
    interface Window { 
      showEventForm(number): void,
      CRM: {
        refreshAllFullCalendarSources(): void 
      } 
    }
}

window.showEventForm = function(event) {
    const unmount = function() {
        ReactDOM.unmountComponentAtNode( document.getElementById('calendar-event-react-app'));
        window.CRM.refreshAllFullCalendarSources()
    }
    unmount();
    ReactDOM.render(<EventEditor unmountCall={unmount} eventId={event.id}/>, document.getElementById('calendar-event-react-app'));
}

class EventEditor extends React.Component<{unmountCall:Function, eventId: number}> {

    render() {
      return (
        <div >
          <EventModal onClose={this.props.unmountCall} eventId={this.props.eventId} />
        </div>
      );
    }
  };