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


/**
 * Generates src/skin/v2/asset-manifest.json after every webpack build.
 *
 * Maps logical bundle names (e.g. "churchcrm.min.css") to their content-hashed
 * filenames (e.g. "churchcrm.a1b2c3d4.min.css") so that PHP's
 * SystemURLs::assetVersioned() can serve the correct immutable-safe URL without
 * knowing the hash at compile time.
 *
 * Why content-hash filenames?
 * CSS/JS bundles previously used static filenames (churchcrm.min.css) with a
 * ?v=mtime query string for cache-busting.  Any CDN or reverse proxy that caches
 * by path (ignoring query strings) would serve stale CSS after an upgrade while
 * the Tabler webfont .woff2 already reflected a new content-hash URL, causing 404s
 * for the old woff2 → blank icon glyphs (issue #9479).
 * Content-hash filenames make the URL itself change on every rebuild, so any web
 * server can safely cache them without risking stale-CSS / missing-font failures.
 */
class AssetManifestPlugin {
  apply(compiler) {
    compiler.hooks.afterEmit.tap('AssetManifestPlugin', (compilation) => {
      const manifest = {};
      // Match content-hashed CSS/JS bundles: name.8hexchars.min.{js,css}
      // Does NOT match assets/ sub-files (fonts, images) — already content-hashed
      // by webpack's asset/resource rule and not served by PHP templates directly.
      const HASHED_BUNDLE = /^(.+?)\.[0-9a-f]{8}(\.min\.(?:js|css))$/;

      for (const asset of compilation.getAssets()) {
        const m = HASHED_BUNDLE.exec(asset.name);
        if (m) {
          const logicalName = m[1] + m[2]; // e.g. "churchcrm.min.css"
          manifest[logicalName] = asset.name; // e.g. "churchcrm.a1b2c3d4.min.css"
        }
      }

      const outFile = path.join(compiler.outputPath, 'asset-manifest.json');
      try {
        fs.writeFileSync(outFile, JSON.stringify(manifest, null, 2));
        console.log(`✅  Asset manifest: ${Object.keys(manifest).length} entries → ${path.relative(process.cwd(), outFile)}`);
      } catch (err) {
        console.warn(`Warning: Could not write asset-manifest.json: ${err.message}`);
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
    'external-calendar': './webpack/external-calendar',
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
    // setup entry keeps a static filename — the setup wizard template
    // (src/setup/templates/header.php) runs before Config.php exists and
    // cannot use SystemURLs::assetVersioned() to resolve manifest entries.
    // Setup is a one-time fresh-install flow so CDN caching is irrelevant.
    filename: (pathData) =>
      pathData.chunk.name === 'setup'
        ? '[name].min.js'
        : '[name].[contenthash:8].min.js',
    // chunkFilename must be set explicitly: when filename is a function webpack
    // cannot infer the async-chunk template from it, so it falls back to [id].js.
    // This restores content-hashed filenames for dynamic chunks (e.g. fc-locale*).
    chunkFilename: '[name].[contenthash:8].min.js',
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
      // Same static-vs-hashed split as output.filename above.
      filename: (pathData) =>
        pathData.chunk.name === 'setup'
          ? '[name].min.css'
          : '[name].[contenthash:8].min.css',
      // chunkFilename must be set explicitly: same reason as output.chunkFilename —
      // MiniCssExtractPlugin defaults to [id].css when filename is a function.
      chunkFilename: '[name].[contenthash:8].min.css',
      ignoreOrder: false,
    }),
    new AssetManifestPlugin(),
    new FixCssUrlQuotesPlugin(),
  ],
};
