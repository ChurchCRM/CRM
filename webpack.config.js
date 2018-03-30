const path = require('path');
module.exports = {
  entry:'./src/react/admin.js',
  output: {
    path:path.resolve('./src/skin/js'),
    filename:'react-app.js'
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