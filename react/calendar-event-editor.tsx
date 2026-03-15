import { createRoot, type Root } from "react-dom/client";
import ExistingEvent from "./components/Events/ExistingEvent";

declare global {
  interface Window {
    // Since TypeScript requires a definition for all methods, let's tell it how to handle the javascript objects already in the page
    showEventForm(object: { id: number }): void;
    showNewEventForm(info: { start: Date; end: Date }): void;
    CRM: {
      // we need to access this method of CRMJSOM, so let's tell TypeScript how to use it
      refreshAllFullCalendarSources(): void;
    };
    // React does have it's own i18next implementation, but for now, lets use the one that's already being loaded
    i18next: {
      t(string: string): string;
    };
    // instead of loading the whole react-moment class, we can just use the one that's already on window.
    moment: unknown;
  }
}

// Store the root instance for unmounting
let reactRoot: Root | null = null;

window.showEventForm = (event) => {
  const container = document.getElementById("calendar-event-react-app");
  if (!container) return;

  const unmount = () => {
    if (reactRoot) {
      reactRoot.unmount();
      reactRoot = null;
    }
    window.CRM.refreshAllFullCalendarSources();
  };

  // Unmount existing component if any
  unmount();

  // Create new root and render
  reactRoot = createRoot(container);
  reactRoot.render(<ExistingEvent onClose={unmount} eventId={event.id} />);
};

window.showNewEventForm = (info) => {
  const { start, end } = info;
  const container = document.getElementById("calendar-event-react-app");
  if (!container) return;

  const unmount = () => {
    if (reactRoot) {
      reactRoot.unmount();
      reactRoot = null;
    }
    window.CRM.refreshAllFullCalendarSources();
  };

  // Unmount existing component if any
  unmount();

  // Create new root and render
  reactRoot = createRoot(container);
  reactRoot.render(<ExistingEvent onClose={unmount} eventId={0} start={start} end={end} />);
};
