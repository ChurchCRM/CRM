<?php

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\FamilyQuery;

//Get the FamilyID out of the querystring
$iFamilyID = FilterInput($_GET['FamilyID'], 'int');

$family =  FamilyQuery::create()
    ->findOneById($iFamilyID);

$family->verify();

header('Location: '.$family->getViewURI());
exit;
