#!/usr/bin/env node

/**
 * Validates YAML files in staged changes.
 * Runs during pre-commit to catch syntax errors early.
 * Prevents invalid workflow files and config from being committed.
 */

const fs = require('fs');
const { execSync } = require('child_process');

// Get list of staged files
let stagedFiles = [];
try {
  const output = execSync('git diff --cached --name-only', { encoding: 'utf-8' });
  stagedFiles = output.trim().split('\n').filter(Boolean);
} catch {
  process.exit(0);
}

// Filter for YAML files
const yamlFiles = stagedFiles.filter(file =>
  file.endsWith('.yml') || file.endsWith('.yaml')
);

if (yamlFiles.length === 0) {
  process.exit(0);
}

let hasErrors = false;

yamlFiles.forEach(file => {
  try {
    const content = fs.readFileSync(file, 'utf-8');

    // Use Node's built-in YAML parsing via require if available
    // Otherwise do basic structural validation
    try {
      // Try to use the yaml package if installed
      const yaml = require('yaml');
      yaml.parse(content);
    } catch (e) {
      // If yaml package isn't available, do basic validation
      // Check for common YAML issues
      const lines = content.split('\n');

      // Look for indentation errors (tabs instead of spaces)
      lines.forEach((line, idx) => {
        if (line.includes('\t')) {
          throw new Error(`Line ${idx + 1}: Contains tabs (must use spaces)`);
        }

        // Check for invalid YAML structure
        const trimmed = line.trim();
        if (trimmed.match(/^[^:]+: *$/) && !trimmed.endsWith('|') && !trimmed.endsWith('>')) {
          // Key with no value and not a multiline indicator
          const nextLine = lines[idx + 1] || '';
          const nextIndent = nextLine.match(/^(\s*)/)[1].length;
          const currentIndent = line.match(/^(\s*)/)[1].length;

          if (nextIndent <= currentIndent && nextLine.trim()) {
            throw new Error(`Line ${idx + 1}: Key "${trimmed}" has no value`);
          }
        }
      });
    }

    console.log(`✓ ${file}`);
  } catch (error) {
    console.error(`✘ ${file}: ${error.message}`);
    hasErrors = true;
  }
});

if (hasErrors) {
  console.error('');
  console.error('✘ YAML validation failed.');
  console.error('  Fix the YAML errors above and re-stage files.');
  console.error('  Bypass only in emergencies: git commit --no-verify (justify in PR)');
  process.exit(1);
}

process.exit(0);
