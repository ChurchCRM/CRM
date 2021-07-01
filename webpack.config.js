const path = require('path');
module.exports = {
  mode: "development",
  entry: {
    'calendar-event-editor' : './react/calendar-event-editor.tsx',
    'two-factor-enrollment' : './react/two-factor-enrollment.tsx'
  },
  output: {
    path:path.resolve('./src/skin/js-react'),
    filename:'[name]-app.js'
  },
  resolve: {
    extensions: [".ts", ".tsx", ".js"]
  },

  module: {
    rules: [
      // all files with a `.ts` or `.tsx` extension will be handled by `ts-loader`
      { 
        test: /\.tsx?$/, 
        loader: "ts-loader" 
      }
    ]
  }
}
