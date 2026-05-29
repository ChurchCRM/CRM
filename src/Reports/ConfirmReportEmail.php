<?php

/**
 * Legacy entry point — redirects to the MVC route.
 *
 * @deprecated Use GET /v2/people/report/verify/email[?familyId=<int>&updated=1] instead.
 */

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$target = SystemURLs::getRootPath() . '/v2/people/report/verify/email';
$params = [];

if (isset($_GET['familyId']) && $_GET['familyId'] !== '') {
    $params['familyId'] = InputUtils::filterInt($_GET['familyId']);
}

if (!empty($_GET['updated'])) {
    $params['updated'] = 1;
}

if (!empty($params)) {
    $target .= '?' . http_build_query($params);
}

RedirectUtils::redirect($target);
