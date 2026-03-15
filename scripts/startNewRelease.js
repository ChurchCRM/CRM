#!/usr/bin/env node

const fs = require("fs").promises;
const { dirname } = require("path");
const { promisify } = require("util");
const exec = promisify(require("child_process").exec);

async function saveJSONFile(fileName, data) {
    // Read original file to detect line endings
    let originalContent = '';
    let lineEnding = '\n'; // Default to Unix
    
    try {
        originalContent = await fs.readFile(fileName, 'utf8');
        // Detect line ending style
        if (originalContent.includes('\r\n')) {
            lineEnding = '\r\n'; // Windows
        } else {
            lineEnding = '\n'; // Unix
        }
    } catch (error) {
        // File might not exist yet, use default Unix line endings
        console.log(`Note: Could not read ${fileName} for line ending detection, using Unix (LF)`);
    }
    
    // For upgrade.json ensure `current` key is always last (human expectations)
    let dataToWrite = data;
    try {
        const normalized = fileName.replace(/\\/g, '/');
        if (normalized.includes('src/mysql/upgrade.json') && data && typeof data === 'object') {
            const ordered = {};
            for (const k of Object.keys(data)) {
                if (k === 'current') continue;
                ordered[k] = data[k];
            }
            if (Object.prototype.hasOwnProperty.call(data, 'current')) {
                ordered.current = data.current;
            }
            dataToWrite = ordered;
        }
    } catch (err) {
        console.log(`Warning: could not reorder keys for ${fileName}: ${err.message}`);
    }

    // Generate JSON with proper formatting
    const jsonContent = JSON.stringify(dataToWrite, null, 4);
    
    // Convert line endings to match original file
    const finalContent = lineEnding === '\r\n' 
        ? jsonContent.replace(/\n/g, '\r\n') 
        : jsonContent;
    
    // Ensure file ends with exactly one newline
    const contentWithEnding = finalContent.replace(/\s+$/, '') + lineEnding;
    
    await fs.writeFile(fileName, contentWithEnding, 'utf8');
}

async function readJSONFile(fileName) {
    const content = await fs.readFile(fileName, "utf8");
    return JSON.parse(content);
}

async function updateVersion(fileName, versionKey, newVersion) {
    const data = await readJSONFile(fileName);
    const oldVersion = data[versionKey];
    console.log(`${fileName} current version: ${oldVersion}`);

    data[versionKey] = newVersion;
    await saveJSONFile(fileName, data);
    console.log(`${fileName} updated to version: ${newVersion}`);
    console.log(`   Line endings preserved for ${fileName}`);

    return oldVersion;
}

async function updateDBVersion(dbFileName, newVersion, oldVersion) {
    const data = await readJSONFile(dbFileName);
    const { current } = data;

    console.log(`Current DB version: ${current.dbVersion}`);

    // Detect whether there are SQL/PHP upgrade scripts for the new version
    let scriptExists = false;
    try {
        const upgradeDir = "src/mysql/upgrade";
        const files = await fs.readdir(upgradeDir);
        scriptExists = files.some((f) => f.startsWith(newVersion));
    } catch (err) {
        console.log(`Could not read upgrade directory for detection: ${err.message}`);
    }

    // If the current block contains upgrade scripts, this means the current
    // release introduced DB changes. Rename the current block to
    // `pre-<current.dbVersion>` and create a fresh `current` block with no scripts
    // and the last DB version as its first `versions` entry.
    if (Array.isArray(current.scripts) && current.scripts.length > 0) {
        const preName = `pre-${current.dbVersion}`;

        if (!data[preName]) {
            // Move the whole current block to pre-<dbVersion>
            data[preName] = {
                versions: Array.isArray(current.versions) ? [...current.versions] : [],
                scripts: Array.isArray(current.scripts) ? [...current.scripts] : [],
                dbVersion: current.dbVersion,
            };
            console.log(`Moved current block to ${preName}`);
        } else {
            // Merge if pre block already exists
            const existing = data[preName];
            existing.versions = Array.from(new Set([...(existing.versions || []), ...(current.versions || [])]));
            existing.scripts = Array.from(new Set([...(existing.scripts || []), ...(current.scripts || [])]));
            existing.dbVersion = existing.dbVersion || current.dbVersion;
            console.log(`Merged current block into existing ${preName}`);
        }

        // Create a new blank current block whose first version is the last DB version
        // and set its dbVersion to the newVersion passed into the release
        data.current = {
            versions: [current.dbVersion],
            scripts: [],
            dbVersion: newVersion,
        };

        await saveJSONFile(dbFileName, data);
        console.log(`${dbFileName} split: created ${preName} and reset current (no SQL).`);
        console.log(`   Line endings preserved for ${dbFileName}`);
        return;
    }

    // If there are no new upgrade scripts and this is the first release after a
    // DB-change (oldVersion equals current DB), create a pre-<newVersion> block
    // with no scripts so the release stands alone.
    if (!scriptExists && oldVersion === current.dbVersion) {
        const blockName = `pre-${newVersion}`;
        if (!data[blockName]) {
            data[blockName] = {
                versions: [oldVersion],
                scripts: [],
                dbVersion: newVersion,
            };
            await saveJSONFile(dbFileName, data);
            console.log(`Created ${blockName} block (no SQL) for version: ${newVersion}`);
            console.log(`   Line endings preserved for ${dbFileName}`);
            return;
        } else {
            console.log(`${blockName} already exists, skipping creation.`);
        }
    }

    // Default behavior: add the old version into the current block and bump dbVersion
    current.dbVersion = newVersion;
    if (!current.versions.includes(oldVersion)) {
        current.versions.push(oldVersion);
    }

    await saveJSONFile(dbFileName, data);
    console.log(`${dbFileName} updated to version: ${newVersion}`);
    console.log(`   Line endings preserved for ${dbFileName}`);
}

async function updateDBDemoSQL(newVersion, oldVersion) {
    const demoSQLFile = "cypress/data/seed.sql";
    
    try {
        console.log(`Updating ${demoSQLFile} with new version entry...`);
        
        // Read the current SQL file as buffer to preserve encoding and line endings
        const sqlBuffer = await fs.readFile(demoSQLFile);
        const sqlContent = sqlBuffer.toString('utf8');
        
        // Detect the line ending style used in the file
        const hasWindowsLineEndings = sqlContent.includes('\r\n');
        const lineEnding = hasWindowsLineEndings ? '\r\n' : '\n';
        
        // Find the INSERT INTO version_ver VALUES line
        const versionInsertRegex = /(INSERT INTO `version_ver` VALUES .*?);/s;
        const match = sqlContent.match(versionInsertRegex);
        
        if (!match) {
            console.error("Could not find version_ver INSERT statement in demo SQL file");
            return;
        }
        
        const currentInsert = match[1];
        
        // Find the highest ID number from existing entries
        const idRegex = /\((\d+),'/g;
        let maxId = 0;
        let idMatch;
        while ((idMatch = idRegex.exec(currentInsert)) !== null) {
            maxId = Math.max(maxId, parseInt(idMatch[1]));
        }
        
        const newId = maxId + 1;
        const newAutoIncrement = newId + 1; // Set AUTO_INCREMENT to next available ID
        const currentDate = new Date().toISOString().slice(0, 19).replace('T', ' ');
        
        // Create the new version entry
        const newVersionEntry = `,(${newId},'${newVersion}','${currentDate}','${currentDate}')`;
        
        // Add the new entry to the existing INSERT statement
        const updatedInsert = currentInsert + newVersionEntry;
        
        // Update the AUTO_INCREMENT value in the CREATE TABLE statement
        const autoIncrementRegex = /(AUTO_INCREMENT=)(\d+)/;
        const updatedSQLWithAutoIncrement = sqlContent.replace(autoIncrementRegex, `$1${newAutoIncrement}`);
        
        // Replace the old INSERT with the updated one
        const finalSQLContent = updatedSQLWithAutoIncrement.replace(versionInsertRegex, `${updatedInsert};`);
        
        // Ensure the file ends with exactly one newline (preserve original ending)
        const trimmedContent = finalSQLContent.replace(/\s+$/, '');
        const finalContent = trimmedContent + lineEnding;
        
        // Write the updated content back to the file with same encoding
        await fs.writeFile(demoSQLFile, finalContent, 'utf8');
        
        console.log(`✅ Added version ${newVersion} (ID: ${newId}) to ${demoSQLFile}`);
        console.log(`   AUTO_INCREMENT updated to: ${newAutoIncrement}`);
        console.log(`   Line ending preserved: ${hasWindowsLineEndings ? 'CRLF' : 'LF'}`);
        
    } catch (error) {
        console.error(`Error updating ${demoSQLFile}:`, error.message);
    }
}

async function updateNpmLock(jsProjectDir) {
    try {
        console.log("Updating package-lock.json...");
        const { stdout, stderr } = await exec("npm install", {
            cwd: jsProjectDir, // Set the working directory to jsProjectDir (./)
        });

        if (stdout) {
            console.log("npm install output:", stdout);
        }
        if (stderr) {
            console.error("npm install error:", stderr);
        }
    } catch (error) {
        console.error("Error updating package-lock.json:", error.message);
    }
}

async function updateNpm(pathToPackageJson, newVersion) {
    const oldVersionPackage = await updateVersion(
        pathToPackageJson,
        "version",
        newVersion,
    );
    await updateNpmLock(dirname(pathToPackageJson));

    return oldVersionPackage;
}

async function updateComposerLock(phpProjectDir) {
    try {
        console.log("Updating composer.lock...");
        const { stdout, stderr } = await exec("composer update --lock", {
            cwd: phpProjectDir, // Set the working directory to phpProjectDir (src)
        });

        if (stdout) {
            console.log("Composer update output:", stdout);
        }
        if (stderr) {
            console.error("Composer update error:", stderr);
        }
    } catch (error) {
        console.error("Error updating composer.lock:", error.message);
    }
}

async function updateComposer(pathToComposerJson, newVersion) {
    const oldVersionPackage = await updateVersion(
        pathToComposerJson,
        "version",
        newVersion,
    );
    await updateComposerLock(dirname(pathToComposerJson));

    return oldVersionPackage;
}

async function main() {
    const [, , newVersion] = process.argv;

    if (!newVersion) {
        console.log("Please pass in a version number");
        process.exit(1);
    }

    console.log(`Starting build: ${newVersion}`);

    const updatePackageFilePromises = [
        updateNpm("package.json", newVersion),
        updateComposer("src/composer.json", newVersion),
    ];

    let oldVersion;
    for (const updatePromise of updatePackageFilePromises) {
        oldVersion = await updatePromise;
    }

    await updateDBVersion("src/mysql/upgrade.json", newVersion, oldVersion);
    await updateDBDemoSQL(newVersion, oldVersion);
}

main();
