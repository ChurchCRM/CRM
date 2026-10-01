#!/usr/bin/env node

/**
 * Post-install patch for cypress-split to format durations as x.y sec
 * Changes humanizeDuration calls to show durations like "15.9 sec" instead of "15.974 seconds"
 */

const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, '../node_modules/cypress-split/src/index.js');

try {
  let content = fs.readFileSync(filePath, 'utf8');

  // Replace humanizeDuration(specDuration) with (specDuration / 1000).toFixed(1) + " sec"
  const oldLine = 'const humanSpecDuration = humanizeDuration(specDuration)';
  const newLine = 'const humanSpecDuration = (specDuration / 1000).toFixed(1) + " sec"';

  if (content.includes(oldLine)) {
    content = content.replace(oldLine, newLine);
    fs.writeFileSync(filePath, content, 'utf8');
    console.log('✓ Patched cypress-split duration formatting');
  } else {
    console.warn('⚠ Could not find expected line in cypress-split/src/index.js');
  }
} catch (error) {
  console.error('Error patching cypress-split:', error.message);
  process.exit(1);
}
