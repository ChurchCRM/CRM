<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$CRMRoot = __DIR__."/src";
$CRMRootLen = strlen($CRMRoot);
$signatureFile = $CRMRoot."/signatures.json";
$composerFile = file_get_contents(__DIR__. "/src/composer.json");
$composerJson = json_decode($composerFile, true);

echo "Creating Signature Definition at: ".$signatureFile."\r\n";

$signatureData = new stdClass();
$signatureData->version = $composerJson["version"];
$signatureData->files =  array();

$projectFiles = new RecursiveDirectoryIterator($CRMRoot);
$Iterator = new RecursiveIteratorIterator($projectFiles);
$Regex = new RegexIterator($Iterator, '/^.+\.(php|js)$/i', RecursiveRegexIterator::GET_MATCH);
foreach ($Regex as $obj )
{
  $file = new stdClass();
  $file->filename = substr($obj[0], $CRMRootLen+1);
  $file->sha1 = sha1_file($CRMRoot."/".$file->filename);
  array_push($signatureData->files, $file);
}

ksort($signatureData->files);

$signatureData->sha1 = sha1(json_encode($signatureData->files));
file_put_contents($signatureFile, json_encode($signatureData));

?>
