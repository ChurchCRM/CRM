import * as React from 'react';
 
class MyDiv extends React.Component<myappprops, myappstate> {
  constructor(props: myappprops) {
    super(props);
    this.state = {
      test: props.test
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
       <MyDiv test="asdf" />
     </div>
     );
  }
};


interface myappprops {
  test: string;
}

interface myappstate {
  test: string;
}