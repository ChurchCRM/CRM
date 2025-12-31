const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const pkg = require('../package.json');
const version = pkg.version || 'unknown';

const outDir = path.join(__dirname, '..', 'temp');
if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}

const outPath = path.join(outDir, `ChurchCRM-${version}.zip`);
const output = fs.createWriteStream(outPath);
const archive = archiver('zip', { zlib: { level: 9 } });

output.on('close', () => {
  const bytes = archive.pointer();
  const kb = (bytes / 1024).toFixed(2);
  const mb = (bytes / 1024 / 1024).toFixed(2);
  console.log(`Created ${path.basename(outPath)} (${mb} MB / ${kb} KB / ${bytes} bytes)`);
});

output.on('error', (err) => {
  console.error('Output stream error:', err);
  process.exit(1);
});

archive.on('warning', function (err) {
  if (err.code === 'ENOENT') {
    console.warn('Archive warning', err);
  } else {
    throw err;
  }
});

archive.on('error', function (err) {
  console.error('Archive error', err);
  process.exit(1);
});

archive.pipe(output);

// Mirror the exclusions used by Grunt's projectFiles (negated patterns)
const ignore = [
  '**/.gitignore',
  '**/.github/**',
  'vendor/**/example/**',
  'vendor/**/tests/**',
  'vendor/**/docs/**',
  'Images/Family/**/*.jpg',
  'Images/Family/**/*.jpeg',
  'Images/Family/**/*.png',
  'Images/Person/**/*.jpg',
  'Images/Person/**/*.jpeg',
  'Images/Person/**/*.png',
  'composer.lock',
  'Include/Config.php',
  'integrityCheck.json',
  'logs/*.log',
  'vendor/endroid/qr-code/assets/fonts/noto_sans.otf',
];

archive.glob('**', {
  cwd: path.join(__dirname, '..', 'src'),
  dot: true,
  ignore: ignore,
}, { prefix: 'churchcrm/' });

archive.finalize();
