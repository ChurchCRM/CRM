<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Step 1 — Show group/role selector
$app->get('/reports', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $groups = GroupQuery::create()->orderByName()->find();

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Group Reports'),
        'sPageSubtitle' => gettext('Generate reports on group membership and activities'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Groups'), '/groups/dashboard'],
            [gettext('Reports')],
        ]),
        'groups' => $groups,
        'step'   => 1,
    ];

    return $renderer->render($response, 'reports.php', $pageArgs);
});

// Step 2 — Show field selector after group/role chosen
$app->post('/reports', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $body      = $request->getParsedBody();
    $iGroupID  = (int) InputUtils::legacyFilterInput($body['GroupID'] ?? '0', 'int');
    $groupRole = InputUtils::legacyFilterInput($body['GroupRole'] ?? '', 'string');

    // Validate group exists — redirect back to step 1 if not
    if ($iGroupID <= 0 || GroupQuery::create()->findPk($iGroupID) === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/groups/reports')
            ->withStatus(302);
    }

    // ReportModel: 1 = filter by selected role, 2 = all roles in group
    $reportModel = ($groupRole !== '' && $groupRole !== '0') ? 1 : 2;

    $propFields = GroupPropMasterQuery::create()
        ->filterByGrpId($iGroupID)
        ->orderByPropId()
        ->find();

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Group Reports'),
        'sPageSubtitle' => gettext('Generate reports on group membership and activities'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Groups'), '/groups/dashboard'],
            [gettext('Reports'), '/groups/reports'],
            [gettext('Select Fields')],
        ]),
        'step'        => 2,
        'iGroupID'    => $iGroupID,
        'groupRole'   => $groupRole,
        'reportModel' => $reportModel,
        'propFields'  => $propFields,
    ];

    return $renderer->render($response, 'reports.php', $pageArgs);
});
