#!/usr/bin/env node

const fs = require("fs").promises;
const { dirname } = require("path");
const { promisify } = require("util");
const exec = promisify(require("child_process").exec);

async function saveJSONFile(fileName, data) {
    await fs.writeFile(fileName, JSON.stringify(data, null, 4));
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

    return oldVersion;
}

async function updateDBVersion(dbFileName, newVersion, oldVersion) {
    const data = await readJSONFile(dbFileName);
    const { current } = data;

    console.log(`Current DB version: ${current.dbVersion}`);
    current.dbVersion = newVersion;
    current.versions.push(oldVersion);

    await saveJSONFile(dbFileName, data);
}

async function updateDBDemoSQL(newVersion, oldVersion) {
    // Implementation pending
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
