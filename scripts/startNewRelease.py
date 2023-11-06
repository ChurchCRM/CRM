import sys
import json

def saveJSONFile(fileName, data):
    with open(fileName, "w") as jsonFile:
        json.dump(data, jsonFile, indent=4)
    return


def readJSONFile(fileName):
    with open(fileName, "r") as jsonFile:
        return json.load(jsonFile)


def updateVersionInFile(fileName, versionFileName, newVersion):
    packageData = readJSONFile(fileName);

    oldVersion = packageData[versionFileName];
    print (fileName, ' current version: ',  oldVersion);
    packageData[versionFileName] = newVersion;
    saveJSONFile(fileName, packageData);
    print (fileName, ' updated to version: ',  newVersion);
    return oldVersion

def updateDBUpdateVersion(newVersion, oldVersion):
    dbFileName = 'src/mysql/upgrade.json';
    data = readJSONFile(dbFileName);
    dbVer = data['current']['dbVersion'];
    print ('Current DB version: ', dbVer );
    data['current']['dbVersion'] = newVersion;
    data['current']['versions'].append(oldVersion);
    saveJSONFile(dbFileName, data);
    return

def updateDBDemoSQL(newVersion, oldVersion):
    dbFileName = 'demo/ChurchCRMDatabase.sql';
    # Not ready
    return;


if len(sys.argv) == 2: 
    newVersion = str(sys.argv[1]);
    print ('Starting build:', newVersion);
    oldVersion = updateVersionInFile('package.json', 'version', newVersion);
    updateVersionInFile('src/composer.json', 'version', newVersion);
    updateDBUpdateVersion(newVersion, oldVersion);
    updateDBDemoSQL(newVersion, oldVersion);

else:
    print ('Please Pass in a version number');


