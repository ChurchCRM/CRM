<?php

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\InputUtils;

//Get the FamilyID out of the querystring
$iFamilyID = InputUtils::LegacyFilterInput($_GET['FamilyID'], 'int');

$family =  FamilyQuery::create()
    ->findOneById($iFamilyID);

$family->verify();

header('Location: '.$family->getViewURI());
exit;
