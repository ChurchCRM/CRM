import * as React from "react";
import * as ReactDOM from "react-dom";
import ExistingEvent from "./components/Events/ExistingEvent";
declare global {
  interface Window {
    // Since TypeScript requires a definition for all methods, let's tell it how to handle the javascript objects already in the page
    showEventForm(object): void;
    showNewEventForm(info): void;
    CRM: {
      // we need to access this method of CRMJSOM, so let's tell TypeScript how to use it
      refreshAllFullCalendarSources(): void;
    };
    // React does have it's own i18next implementation, but for now, lets use the one that's already being loaded
    i18next: {
      t(string): string;
    };
    // instead of loading the whole react-moment class, we can just use the one that's already on window.
    moment: any;
  }
}

window.showEventForm = function (event) {
  const unmount = function () {
    ReactDOM.unmountComponentAtNode(
      document.getElementById("calendar-event-react-app"),
    );
    window.CRM.refreshAllFullCalendarSources();
  };
  unmount();
  ReactDOM.render(
    <ExistingEvent onClose={unmount} eventId={event.id} />,
    document.getElementById("calendar-event-react-app"),
  );
};

window.showNewEventForm = function (info) {
  const { start, end } = info;
  const unmount = function () {
    ReactDOM.unmountComponentAtNode(
      document.getElementById("calendar-event-react-app"),
    );
    window.CRM.refreshAllFullCalendarSources();
  };
  unmount();
  ReactDOM.render(
    <ExistingEvent
      onClose={unmount}
      eventId={0}
      start={start}
      end={end}
    />,
    document.getElementById("calendar-event-react-app"),
  );
};
