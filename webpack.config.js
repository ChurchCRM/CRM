const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const webpack = require('webpack');

module.exports = {
  mode: "development",
  entry: {
    'calendar-event-editor' : './react/calendar-event-editor.tsx',
    'two-factor-enrollment' : './react/two-factor-enrollment.tsx',
    'churchcrm' : './webpack/skin-main'  // Main bundle for all pages
  },
  output: {
    path:path.resolve('./src/skin/v2'),
    filename:'[name].min.js'
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
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              url: {
                filter: (url) => {
                  // Only process relative URLs, skip absolute paths and data URIs
                  return !url.startsWith('/') && !url.startsWith('data:');
                },
              },
            },
          },
          'sass-loader',
        ],
      },
      {
        test: /\.(woff2?|ttf|eot|svg|png|jpg|gif)$/,
        type: 'asset/resource',
        generator: {
          filename: 'assets/[name].[hash][ext]',
        },
      },
    ]
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: '[name].min.css',
      ignoreOrder: false,
    }),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
      'window.jQuery': 'jquery',
      'window.$': 'jquery',
    }),
  ],
}
