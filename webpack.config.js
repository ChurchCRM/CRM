const path = require('path');
module.exports = {
  entry: {
    //define entry poins for react apps.
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