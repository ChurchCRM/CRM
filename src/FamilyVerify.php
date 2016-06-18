<?php

require "Include/Config.php";
require "Include/Functions.php";
require "Service/NoteService.php";
require "Service/FamilyService.php";

$noteService = new NoteService();
$familyService = new FamilyService();

//Get the FamilyID out of the querystring
$iFamilyID = FilterInput($_GET["FamilyID"], 'int');

$noteService->addNote(0, $iFamilyID, 0, "Family Data Verified", "verify");

$familyURI = $familyService->getViewURI($iFamilyID);

Header("Location: " . $familyURI);
exit;


