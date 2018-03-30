const path = require('path');
module.exports = {
  entry: {
    'calendar-event-editor' : './src/react/calendar-event-editor.js'
  },
  output: {
    path:path.resolve('./src/skin/js-react'),
    filename:'[name]-app.js'
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader"
        }
      }
    ]
  }
}