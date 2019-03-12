import * as React from 'react';
import * as ReactDOM from 'react-dom';
import CRMEvent from './interfaces/CRMEvent';
import ExistingEvent from './components/ExistingEvent';
declare global {
    interface Moment {
      _isAMomentObject: boolean,
      hasTime(): boolean,
      format(): string;
    }
    interface Window { 
      // Since TypeScript requires a definition for all methods, let's tell it how to handle the javascript objects already in the page 
      showEventForm(object): void,
      showNewEventForm(start: Moment,end: Moment): void,
      CRM: {
        // we need to access this method of CRMJSOM, so let's tell TypeScript how to use it
        refreshAllFullCalendarSources(): void 
      },
      // React does have it's own i18next implementation, but for now, lets use the one that's already being loaded
      i18next: { 
        t(string): string
      },
      // instead of loading the whole react-moment class, we can just use the one that's already on window.
      moment: any
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

function FullCalendarToLocalizedDate(input:Moment) : Date {
  var offsetStr = "";
  if(!input.hasTime())
  {
    // if the input string from FullCalendar does not have a time,
    // then append TZdata like GMT-0500
    // Get the browser's timezone https://stackoverflow.com/a/15304657
    offsetStr = " " + new Date().toString().match(/([A-Z]+[\+-][0-9]+)/)[1];
  }
  else {
    // if the input string from FullCalendar does have a time,
    // then append TZdata like -0500
    offsetStr = new Date().toString().match(/([-\+][0-9]+)\s/)[1];
  }
  // since new Date(string) stores data in UTC, we need to instruct it
  // that the date string we're feeding it belongs to a specific timezone
  // end result here for time-less:  2019-02-11 GMT-0500
  // or for timed: 2019-02-19T09:00:00-0500
  var datestr = input.format() + offsetStr;
  var d = new Date(datestr);
  return d;
}

window.showNewEventForm = function(start,end) {
  const unmount = function() {
    ReactDOM.unmountComponentAtNode( document.getElementById('calendar-event-react-app'));
    window.CRM.refreshAllFullCalendarSources()
  }
  unmount(); 
  ReactDOM.render(<ExistingEvent onClose={unmount} eventId={0} start={FullCalendarToLocalizedDate(start)} end={FullCalendarToLocalizedDate(end)} />, document.getElementById('calendar-event-react-app'));
}
