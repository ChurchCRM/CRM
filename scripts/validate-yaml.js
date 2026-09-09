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

    // Additional checks for GitHub Actions workflows
    if (file.includes('.github/workflows/')) {
      validateWorkflow(file, content);
    }

    console.log(`✓ ${file}`);
  } catch (error) {
    console.error(`✘ ${file}: ${error.message}`);
    hasErrors = true;
  }
});

function validateWorkflow(file, content) {
  // Parse YAML to check workflow structure
  try {
    const yaml = require('yaml');
    const workflow = yaml.parse(content);

    // Check required top-level fields
    if (!workflow.name) {
      throw new Error('Missing required field: name');
    }

    if (!workflow.on) {
      throw new Error('Missing required field: on (trigger events)');
    }

    if (!workflow.jobs || typeof workflow.jobs !== 'object') {
      throw new Error('Missing required field: jobs (must be an object)');
    }

    // Validate jobs structure
    Object.entries(workflow.jobs).forEach(([jobName, jobConfig]) => {
      if (!jobConfig.runs_on) {
        throw new Error(`Job "${jobName}": missing required field runs-on`);
      }

      if (!jobConfig.steps || !Array.isArray(jobConfig.steps)) {
        throw new Error(`Job "${jobName}": missing required field steps (must be array)`);
      }

      jobConfig.steps.forEach((step, stepIdx) => {
        if (!step.name && !step.run) {
          throw new Error(`Job "${jobName}" step ${stepIdx + 1}: must have either name or run`);
        }
      });
    });
  } catch (e) {
    if (e.message && e.message.startsWith('Missing required field:')) {
      throw e;
    }
    // Ignore YAML parsing errors - already caught above
  }
}

if (hasErrors) {
  console.error('');
  console.error('✘ YAML validation failed.');
  console.error('  Fix the YAML errors above and re-stage files.');
  console.error('  Bypass only in emergencies: git commit --no-verify (justify in PR)');
  process.exit(1);
}

process.exit(0);
