const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
  mode: isProduction ? 'production' : 'development',
  entry: {
    'calendar-event-editor': './react/calendar-event-editor.tsx',
    'two-factor-enrollment': './react/two-factor-enrollment.tsx',
    churchcrm: './webpack/skin-main',
    'photo-uploader': './webpack/photo-uploader-entry',
    setup: './webpack/setup',
    'family-register': './webpack/family-register',
    'family-verify': './webpack/family-verify',
    'upgrade-wizard': './webpack/upgrade-wizard',
    'locale-loader': './webpack/locale-loader',
    backup: './webpack/backup',
    restore: './webpack/restore',
    'admin-dashboard': './webpack/admin-dashboard',
    'system-settings-panel': './webpack/system-settings-panel',
    'kiosk-registration-closed': './webpack/kiosk-registration-closed',
    kiosk: './webpack/kiosk',
    'people-list': './webpack/people/person-list',
    'people-family-list': './webpack/people/family-list',
  },
  output: {
    path: path.resolve('./src/skin/v2'),
    filename: '[name].min.js',
    publicPath: 'auto',
  },
  resolve: {
    extensions: ['.ts', '.tsx', '.js'],
    alias: {
      jquery: path.resolve(__dirname, 'node_modules/jquery'),
    },
  },
  cache: {
    type: 'filesystem',
    buildDependencies: {
      config: [__filename],
    },
  },
  devtool: isProduction ? false : 'eval-cheap-module-source-map',
  optimization: {
    moduleIds: 'deterministic',
    chunkIds: 'deterministic',
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        loader: 'ts-loader',
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
          filename: 'assets/[name].[contenthash][ext]',
        },
      },
    ],
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: '[name].min.css',
      ignoreOrder: false,
    }),
  ],
};
