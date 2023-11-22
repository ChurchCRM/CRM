<?php

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\InputUtils;

//Get the FamilyID out of the querystring
$iFamilyID = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');

$family = FamilyQuery::create()
    ->findOneById($iFamilyID);

$family->verify();

header('Location: ' . $family->getViewURI());
exit;
