<?php

/**
 * Legacy entry point — redirects to the MVC route.
 *
 * @deprecated Use GET /v2/people/report/verify[?familyId=<int>] instead.
 */

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$target = SystemURLs::getRootPath() . '/v2/people/report/verify';

if (isset($_GET['familyId']) && $_GET['familyId'] !== '') {
    $familyId = InputUtils::filterInt($_GET['familyId']);
    $target .= '?familyId=' . $familyId;
}

RedirectUtils::redirect($target);
