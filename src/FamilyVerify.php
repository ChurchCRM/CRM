<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\InputUtils;

// Get the FamilyID out of the querystring
$iFamilyID = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');

$family = FamilyQuery::create()
    ->findOneById($iFamilyID);

$family->verify();

header('Location: ' . $family->getViewURI());
exit;
