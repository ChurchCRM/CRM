#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('🔍 PHP Syntax Validation');
console.log('========================\n');

// Read signatures file - use the actual signatures.json from build:signatures
let signaturesPath = path.join(__dirname, '../src/admin/data/signatures.json');

if (!fs.existsSync(signaturesPath)) {
    console.error('❌ Error: signatures.json not found');
    console.error('   Run "npm run build:signatures" first');
    process.exit(1);
}

const signatures = JSON.parse(fs.readFileSync(signaturesPath, 'utf8'));

if (!signatures.files || !Array.isArray(signatures.files)) {
    console.error('❌ Error: Invalid signatures file format');
    process.exit(1);
}

console.log(`📋 Found ${signatures.files.length} files in signatures\n`);

let errors = 0;
let validated = 0;
let skipped = 0;
let notFound = 0;

// Validate each PHP file
for (const fileObj of signatures.files) {
    // Handle both object format (from signatures.json) and string format (legacy)
    const file = typeof fileObj === 'string' ? fileObj : fileObj.filename;
    
    // Only validate PHP files (skip JS files - they're minified/bundled)
    if (!file.endsWith('.php')) {
        skipped++;
        continue;
    }
    
    // Skip vendor files - they are third-party and validation is handled by composer
    if (file.includes('/vendor/') || file.startsWith('vendor/')) {
        skipped++;
        continue;
    }
    
    // Convert forward slashes to correct path separators and prepend src/
    const filePath = path.join(__dirname, '../src', file.replace(/\//g, path.sep));
    
    if (!fs.existsSync(filePath)) {
        notFound++;
        continue;
    }
    
    try {
        // Run php -l on the file
        execSync(`php -l "${filePath}"`, { 
            stdio: 'pipe',
            encoding: 'utf8'
        });
        validated++;
        process.stdout.write('.');
        
        // Print progress every 50 files
        if (validated % 50 === 0) {
            console.log(` ${validated}/${signatures.files.length}`);
        }
    } catch (error) {
        errors++;
        console.log(`\n❌ SYNTAX ERROR: ${file}`);
        console.log(error.stderr || error.message);
    }
}

console.log(`\n\n${'='.repeat(60)}`);
console.log(`✅ Validated: ${validated} PHP files`);
console.log(`⏭️  Skipped: ${skipped} non-PHP files`);
if (notFound > 0) {
    console.log(`⚠️  Not found: ${notFound} PHP files`);
}
if (errors > 0) {
    console.log(`❌ Errors: ${errors} files`);
    console.log('='.repeat(60));
    process.exit(1);
} else {
    console.log(`✨ All PHP files passed syntax validation!`);
    console.log('='.repeat(60));
}
