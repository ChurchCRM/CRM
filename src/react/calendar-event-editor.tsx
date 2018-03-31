import * as React from 'react';
import * as ReactDOM from 'react-dom';
import App from './components/admin-app';

declare global {
    interface Window { showEventForm: Function; }
}

window.showEventForm = function(eventId) {
    const unmount = function() {
        ReactDOM.unmountComponentAtNode( document.getElementById('react-app'));
    }
    unmount();
    ReactDOM.render(<App unmountCall={unmount} eventId={eventId}/>, document.getElementById('react-app'));
}

$("#render").click(function() {
    window.showEventForm(2);
});