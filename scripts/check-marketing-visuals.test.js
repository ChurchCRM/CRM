const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { test } = require('node:test');
const { PNG } = require('pngjs');

// Synthetic fixtures live only in a temporary directory, outside publish artifacts.
function fixture(t, { width = 2880, height = 1800, blank = false, corrupt = false } = {}) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'visual-guard-fixture-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  const imageDir = path.join(root, 'screenshots/en/desktop');
  const metadataDir = path.join(root, 'metadata/en/desktop');
  fs.mkdirSync(imageDir, { recursive: true });
  fs.mkdirSync(metadataDir, { recursive: true });
  const image = new PNG({ width, height });
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const offset = (y * width + x) * 4;
      const value = blank ? 255 : (Math.floor(x / 20) + Math.floor(y / 20)) % 2 * 200;
      image.data.fill(value, offset, offset + 3);
      image.data[offset + 3] = 255;
    }
  }
  const file = path.join(imageDir, 'synthetic-fixture.png');
  fs.writeFileSync(file, corrupt ? Buffer.alloc(6000) : PNG.sync.write(image));
  assert.ok(fs.statSync(file).size > 5120, 'fixture must pass the old size guard');
  fs.writeFileSync(path.join(metadataDir, 'synthetic-fixture.json'), JSON.stringify({
    workflow: 'synthetic-fixture', title: 'Synthetic fixture', category: 'Fixtures',
    artifact: 'synthetic-fixture.png', viewport: { width: 1440, height: 900 },
  }));
  return root;
}

function check(root) {
  return spawnSync(process.execPath, [path.join(__dirname, 'check-marketing-visuals.js'), root], { encoding: 'utf8' });
}

test('viewport-sized nonblank fixture passes', (t) => {
  const result = check(fixture(t));
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /verified 1 artifact/);
});

test('blank PNG fails even when it passes the file-size guard', (t) => {
  const result = check(fixture(t, { blank: true }));
  assert.equal(result.status, 1);
  assert.match(result.stderr, /BLANK.*0\/16 tiles/);
});

test('cropped PNG fails even when it passes the file-size guard', (t) => {
  const result = check(fixture(t, { height: 900 }));
  assert.equal(result.status, 1);
  assert.match(result.stderr, /WRONG DIMENSIONS.*2880x900, expected 2880x1800/);
});

test('full-page PNG fails the viewport-height guard', (t) => {
  const result = check(fixture(t, { height: 2700 }));
  assert.equal(result.status, 1);
  assert.match(result.stderr, /WRONG DIMENSIONS/);
});

test('invalid PNG is reported as a problem', (t) => {
  const result = check(fixture(t, { corrupt: true }));
  assert.equal(result.status, 1);
  assert.match(result.stderr, /INVALID PNG/);
});
