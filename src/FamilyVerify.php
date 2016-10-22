<?php

require "Include/Config.php";
require "Include/Functions.php";

use ChurchCRM\Note;
use ChurchCRM\Service\FamilyService;

$familyService = new FamilyService();

//Get the FamilyID out of the querystring
$iFamilyID = FilterInput($_GET["FamilyID"], 'int');

$note = new Note();
$note->setFamId($iFamilyID);
$note->setText("Family Data Verified");
$note->setType("verify");
$note->setEntered($_SESSION['iUserID']);
$note->save();

$familyURI = $familyService->getViewURI($iFamilyID);

Header("Location: " . $familyURI);
exit;


