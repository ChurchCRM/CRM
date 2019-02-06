import * as React from 'react';
import * as ReactDOM from 'react-dom';
import CRMEvent from './interfaces/CRMEvent';
import ExistingEvent from './components/ExistingEvent';
declare global {
    interface Window { 
      // Since TypeScript requires a definition for all methods, let's tell it how to handle the javascript objects already in the page 
      showEventForm(object): void,
      showNewEventForm(start: string,end: string): void,
      CRM: {
        // we need to access this method of CRMJSOM, so let's tell TypeScript how to use it
        refreshAllFullCalendarSources(): void 
      },
      // React does have it's own i18next implementation, but for now, lets use the one that's already being loaded
      i18next: { 
        t(string): string
      }
    }
}

window.showEventForm = function(event) {
    const unmount = function() {
        ReactDOM.unmountComponentAtNode( document.getElementById('calendar-event-react-app'));
        window.CRM.refreshAllFullCalendarSources()
    }
    unmount();
    ReactDOM.render(<ExistingEvent onClose={unmount} eventId={event.id}/>, document.getElementById('calendar-event-react-app'));
}

window.showNewEventForm = function(start,end) {
  const unmount = function() {
    ReactDOM.unmountComponentAtNode( document.getElementById('calendar-event-react-app'));
    window.CRM.refreshAllFullCalendarSources()
  }
  unmount();
  ReactDOM.render(<ExistingEvent onClose={unmount} eventId={0} />, document.getElementById('calendar-event-react-app'));
}
