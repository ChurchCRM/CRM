import * as React from 'react';
import * as ReactDOM from 'react-dom';
import App from './components/admin-app';


$("#render").click(function() {
    console.log("test");
    ReactDOM.render(<App />, document.getElementById('react-app'));
});



$("#remove").click(function() {
    console.log("test");
    ReactDOM.unmountComponentAtNode(document.getElementById('react-app'));
});

