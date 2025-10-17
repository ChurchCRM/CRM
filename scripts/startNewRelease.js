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
    
    // Generate JSON with proper formatting
    const jsonContent = JSON.stringify(data, null, 4);
    
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
    current.dbVersion = newVersion;
    current.versions.push(oldVersion);

    await saveJSONFile(dbFileName, data);
    console.log(`${dbFileName} updated to version: ${newVersion}`);
    console.log(`   Line endings preserved for ${dbFileName}`);
}

async function updateDBDemoSQL(newVersion, oldVersion) {
    const demoSQLFile = "demo/ChurchCRM-Database.sql";
    
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
