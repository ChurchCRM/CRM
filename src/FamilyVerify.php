<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Get the FamilyID out of the querystring
$iFamilyID = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');

$family = FamilyQuery::create()
    ->findOneById($iFamilyID);

$family->verify();

RedirectUtils::redirect($family->getViewURI());
