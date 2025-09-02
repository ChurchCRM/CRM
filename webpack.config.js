const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const webpack = require('webpack');

module.exports = {
  mode: "development",
  entry: {
    'calendar-event-editor' : './react/calendar-event-editor.tsx',
    'two-factor-enrollment' : './react/two-factor-enrollment.tsx',
    'skin-main' : './webpack/skin-main',
    'skin-loggedout' : './webpack/skin-loggedout'
  },
  output: {
    path:path.resolve('./src/skin/v2'),
    filename:'[name].js'
  },
  resolve: {
    extensions: [".ts", ".tsx", ".js"],
    alias: {
      jquery: path.resolve(__dirname, 'node_modules/jquery'),
    },
  },

  module: {
    rules: [
      // all files with a `.ts` or `.tsx` extension will be handled by `ts-loader`
      { 
        test: /\.tsx?$/, 
        loader: "ts-loader" 
      },
      {
        test: /\.(sa|sc|c)ss$/,
        use: [MiniCssExtractPlugin.loader, 'css-loader', 'sass-loader'],
      },
      {
        test: /\.(woff2?|ttf|eot|svg|png|jpg|gif)$/,
        type: 'asset/resource',
      },
    ]
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: '[name].css',
    }),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
      'window.jQuery': 'jquery',
      'window.$': 'jquery',
    }),
  ],
}
