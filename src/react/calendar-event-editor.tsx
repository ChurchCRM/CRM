import * as React from 'react';
import * as ReactDOM from 'react-dom';
import CRMEvent from './interfaces/CRMEvent';
import EventModal from './components/EventModal';
declare global {
    interface Window { showEventForm: Function; }
}

window.showEventForm = function(eventId, refreshCallback) {
    const unmount = function() {
        ReactDOM.unmountComponentAtNode( document.getElementById('calendar-event-react-app'));
        refreshCallback()
    }
    unmount();
    ReactDOM.render(<App unmountCall={unmount} eventId={eventId}/>, document.getElementById('calendar-event-react-app'));
}

class App extends React.Component<{unmountCall:Function, eventId: number}> {

    render() {
      return (
        <div >
          <EventModal onClose={this.props.unmountCall} eventId={this.props.eventId} />
        </div>
      );
    }
  };