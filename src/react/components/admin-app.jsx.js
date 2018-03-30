import React from 'react';
 
class MyDiv extends React.Component {
  constructor() {
    super();
    this.state = {
      test: "loading"
    };
  }
  componentDidMount() {
    fetch("/api/public/data/countries")
      .then(response => response.json())
      .then(data => this.setState({ test:"loaded" }));
  }
  render() {
    return (
      <div style={{textAlign: 'center'}}>
        <h1>Hello World - this is rendered by react {this.state.test}</h1>
      </div>
    );
  }
 }

  
export default class App extends React.Component {
  render() {
    return (
     <div >
       <MyDiv />
       <MyDiv />
     </div>
     );
  }
};

