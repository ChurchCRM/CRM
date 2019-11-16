<?php

function ConsoleWriteLine($string) {
    echo $string."\n";
}

function GetDemoKey() {
    $buildconfigs = \json_decode(file_get_contents("BuildConfig.json"), true);
    if (!empty($buildconfigs['demoKey'])){
        ConsoleWriteLine("Using demoKey from buildconfig.json");
        return $buildconfigs['demoKey'];
    }
    elseif (!empty(getenv('demoKey'))) {
        ConsoleWriteLine("Using demoKey from environment variables");
        return getenv('demoKey');
    }
    else {
        throw new \Exception("No demoKey could be found");
    }
}

function GetBranchName() {
    if (!empty(getenv("TRAVIS_BRANCH"))) {
        return getenv("TRAVIS_BRANCH");
    }
    else {
        return exec("git rev-parse --abbrev-ref HEAD");
    }
}

$buildVersion = $buildconfigs = \json_decode(file_get_contents("package.json"), true)['version'];
$uploadFile = "target/ChurchCRM-".$buildVersion.".zip";
$currentBranch = GetBranchName();
$commitHash = exec("git log --pretty=format:%H -n 1");

ConsoleWriteLine("Uploading $uploadFile to demosite as $currentBranch with hash: $commitHash");
// initialise the curl request
$request = curl_init('http://demo.churchcrm.io/webhooks/DemoUpdate.php');

// send a file
curl_setopt($request, CURLOPT_POST, true);
curl_setopt(
    $request,
    CURLOPT_POSTFIELDS,
    array(
      'fileupload' => curl_file_create($uploadFile),
      'branch' => $currentBranch,
      'commitHash' => $commitHash,
      'demoKey' => GetDemoKey()
    ));

// output the response
curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($request);
print_r($result);

// close the session
curl_close($request);