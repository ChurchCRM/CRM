const path = require('path');
const fs = require('fs');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const isProduction = process.env.NODE_ENV === 'production';

// Plugin to fix unquoted URLs in CSS (especially fonts)
class FixCssUrlQuotesPlugin {
  apply(compiler) {
    compiler.hooks.afterEmit.tap('FixCssUrlQuotesPlugin', (compilation) => {
      const outputPath = compiler.outputPath;
      const missingFiles = [];

      compilation.getAssets().forEach((asset) => {
        if (asset.name.endsWith('.css')) {
          const filePath = path.join(outputPath, asset.name);
          try {
            let content = fs.readFileSync(filePath, 'utf-8');

            // Check for missing font/asset files referenced in url() declarations
            const urlMatches = content.matchAll(/url\(["']?([^"')\s]+)["']?\)/g);
            for (const match of urlMatches) {
              const urlPath = match[1];
              // Only check relative URLs (not data:, external, or absolute paths)
              if (!urlPath.startsWith('data:') && !urlPath.startsWith('http') && !urlPath.startsWith('//') && !urlPath.startsWith('/')) {
                // Strip query-string and fragment before testing for file existence
                const cleanPath = urlPath.split('?')[0].split('#')[0];
                const fullPath = path.join(outputPath, cleanPath);
                try {
                  fs.accessSync(fullPath, fs.constants.F_OK);
                } catch (accessErr) {
                  if (accessErr.code === 'ENOENT') {
                    missingFiles.push({ file: asset.name, url: urlPath, fullPath });
                  }
                  // else: EPERM / EINVAL — file may exist but be unreadable; skip silently
                }
              }
            }

            // Add quotes around unquoted URLs; only write when something changed
            // to avoid unnecessary I/O and mtime churn on unchanged CSS files.
            // Note: the regex [^'")\ s] already excludes quote chars, so every captured
            // url is unquoted — no dead-code guard needed.
            const fixed = content.replace(
              /url\(([^'")\s]+)\)/g,
              (_match, url) => `url("${url}")`
            );
            if (fixed !== content) {
              fs.writeFileSync(filePath, fixed, 'utf-8');
            }
          } catch (err) {
            // Skip if file cannot be read (might have been deleted between emit and this hook)
            console.warn(`Warning: Could not process CSS file ${asset.name}: ${err.message}`);
          }
        }
      });

      // Report missing files as errors
      if (missingFiles.length > 0) {
        const errorMsg = missingFiles
          .map((f) => `  ❌ ${f.file}: url("${f.url}") → ${f.fullPath}`)
          .join('\n');
        console.error('\n⚠️  Missing font/asset files referenced in CSS:\n' + errorMsg + '\n');
        compilation.errors.push(new Error(`${missingFiles.length} missing asset file(s) referenced in CSS`));
      }
    });
  }
}

module.exports = {
  mode: isProduction ? 'production' : 'development',
  entry: {
    'calendar-event-editor': './webpack/calendar-event-editor.js',
    'two-factor-enrollment': './webpack/two-factor-enrollment.js',
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
    localization: './webpack/localization',
    'system-settings-panel': './webpack/system-settings-panel',
    'kiosk-registration-closed': './webpack/kiosk-registration-closed',
    kiosk: './webpack/kiosk',
    'people-list': './webpack/people/person-list',
    'people-family-list': './webpack/people/family-list',
    'people-family-view': './webpack/people/family-view',
    'people-person-view': './webpack/people/person-view',
    'error': './webpack/error',
    'groups-sundayschool-dashboard': './webpack/groups-sundayschool-dashboard',
    'groups-sundayschool-class-view': './webpack/groups-sundayschool-class-view',
    'repeat-event-editor': './webpack/repeat-event-editor',
    'event-checkin': './webpack/event-checkin',
    'event-calendars': './webpack/event-calendars',
    'event-types': './webpack/event-types',
    'event-editor': './webpack/event-editor',
    'event-types-list': './webpack/event-types-list',
    'event-cart-to-event': './webpack/event-cart-to-event',
    'email-composer': './webpack/common/email-composer',
    'telemetry': './webpack/telemetry',
    'debug': './webpack/debug',
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
    new FixCssUrlQuotesPlugin(),
  ],
};
