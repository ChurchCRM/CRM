const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const webpack = require('webpack');

module.exports = {
  mode: "development",
  entry: {
    'calendar-event-editor': './react/calendar-event-editor.tsx',
    'two-factor-enrollment': './react/two-factor-enrollment.tsx',
    'churchcrm': './webpack/skin-main',  // Main bundle for all pages
    'photo-uploader': './webpack/photo-uploader-entry',  // Photo uploader for specific pages
    'setup': './webpack/setup',  // Setup wizard styles
    'family-register': './webpack/family-register',  // Family registration styles and scripts
    'upgrade-wizard': './webpack/upgrade-wizard',  // Upgrade wizard styles and scripts
    'locale-loader': './webpack/locale-loader',  // Dynamic locale loader
    'backup': './webpack/backup',  // Backup database page
    'restore': './webpack/restore',  // Restore database page
    'admin-dashboard': './webpack/admin-dashboard',  // Admin dashboard page styles and scripts
    'system-settings-panel': './webpack/system-settings-panel'  // Reusable settings panel component
  },
  output: {
    path: path.resolve('./src/skin/v2'),
    filename: '[name].min.js',
    publicPath: 'auto'  // Auto-detect public path based on script location
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
  ],
}
