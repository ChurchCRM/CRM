const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const webpack = require('webpack');

module.exports = {
  mode: "development",
  entry: {
    'calendar-event-editor' : './react/calendar-event-editor.tsx',
    'two-factor-enrollment' : './react/two-factor-enrollment.tsx',
    'churchcrm' : './webpack/skin-main'
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
        // SCSS files: process with sass-loader
        test: /\.scss$/,
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
        // CSS files: do NOT process with sass-loader, just pass through loaders
        test: /\.css$/,
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
        // Output churchcrm bundle CSS as churchcrm.min.css
        if (pathData.chunk.name === 'churchcrm') {
          return 'churchcrm.min.css';
        }
        return '[name].css';
      },
      ignoreOrder: true,
    }),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
      'window.jQuery': 'jquery',
      'window.$': 'jquery',
    }),
  ],
}
