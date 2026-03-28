const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
  mode: isProduction ? 'production' : 'development',
  entry: {
    'calendar-event-editor': './react/calendar-event-editor.tsx',
    'two-factor-enrollment': './react/two-factor-enrollment.tsx',
    churchcrm: './webpack/skin-main',
    'churchcrm-rtl': './webpack/skin-rtl',
    'photo-uploader': './webpack/photo-uploader-entry',
    'root-dashboard': './webpack/root-dashboard',
    setup: './webpack/setup',
    'family-register': './webpack/family-register',
    'family-verify': './webpack/family-verify',
    'upgrade-wizard': './webpack/upgrade-wizard',
    'locale-loader': './webpack/locale-loader',
    backup: './webpack/backup',
    restore: './webpack/restore',
    'csv-import': './webpack/csv-import',
    'admin-dashboard': './webpack/admin-dashboard',
    'get-started': './webpack/get-started',
    'church-info': './webpack/church-info',
    'system-settings-panel': './webpack/system-settings-panel',
    'kiosk-registration-closed': './webpack/kiosk-registration-closed',
    kiosk: './webpack/kiosk',
    'people-list': './webpack/people/person-list',
    'people-family-list': './webpack/people/family-list',
    'people-family-view': './webpack/people/family-view',
    'people-person-view': './webpack/people/person-view',
    'groups-sundayschool-dashboard': './webpack/groups-sundayschool-dashboard',
    'groups-sundayschool-class-view': './webpack/groups-sundayschool-class-view',
  },
  output: {
    path: path.resolve('./src/skin/v2'),
    filename: '[name].min.js',
    publicPath: 'auto',
  },
  externals: {
    // Leaflet is loaded as a global from skin/external/leaflet/leaflet.js (Grunt-copied).
    // Mapping it here lets webpack entries import 'leaflet' without bundling it.
    leaflet: 'L',
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
  // Silence react-datepicker dynamic require warning while keeping other warnings visible
  ignoreWarnings: [
    {
      module: /react-datepicker/,
      message: /Critical dependency: the request of a dependency is an expression/,
    },
  ],
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
