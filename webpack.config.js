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
      filename: (pathData) => {
        // Output both skin-main and skin-loggedout SCSS as churchcrm.min.css
        // Both entries import the same SCSS, so they generate identical CSS
        if (pathData.chunk.name === 'skin-main' || pathData.chunk.name === 'skin-loggedout') {
          return 'churchcrm.min.css';
        }
        return '[name].css';
      },
    }),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
      'window.jQuery': 'jquery',
      'window.$': 'jquery',
    }),
  ],
}
