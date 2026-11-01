<?php

use ChurchCRM\Utils\PdfResponse;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Service\GroupReportService;
use ChurchCRM\Service\MpdfRenderer;
use ChurchCRM\Service\PhotoBookService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use ChurchCRM\Utils\PdfFilename;
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
            [gettext('Group Reports')],
        ]),
        'groups' => $groups,
        'step'   => 1,
        'sundaySchoolEnabled' => SystemConfig::getBooleanValue('bEnabledSundaySchool'),
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

    $propFields = GroupPropMasterQuery::create()
        ->filterByGrpId($iGroupID)
        ->orderByPropId()
        ->find();

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Group Reports'),
        'sPageSubtitle' => gettext('Generate reports on group membership and activities'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Group Reports'), '/groups/reports'],
            [gettext('Select Fields')],
        ]),
        'step'        => 2,
        'iGroupID'    => $iGroupID,
        'groupRole'   => $groupRole,
        'propFields'  => $propFields,
    ];

    return $renderer->render($response, 'reports.php', $pageArgs);
});

// Step 3 — Group directory PDF. Needs the group-management role of this app.
$app->post('/reports/group-directory', function (Request $request, Response $response) {
    $body = (array) $request->getParsedBody();
    $groupId = InputUtils::filterInt($body['GroupID'] ?? '');
    $roleId = InputUtils::filterInt($body['GroupRole'] ?? '');

    $fields = array_values(array_filter(GroupReportService::FIELDS, fn (string $field) => !empty($body[$field . 'Enable'])));
    $propertyIds = [];
    foreach (array_keys($body) as $name) {
        if (preg_match('/^(c\d+)enable$/', (string) $name, $match) === 1) {
            $propertyIds[] = $match[1];
        }
    }
    $onlyPersonIds = !empty($body['OnlyCart']) ? array_map('intval', array_filter(explode(',', Cart::getCartIdString()))) : null;

    $report = (new GroupReportService())->build($groupId, $roleId, $fields, $propertyIds, $onlyPersonIds);
    if ($report === null) {
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/groups/reports')
            ->withStatus(302);
    }

    $pdf = (new MpdfRenderer())->renderToString('group-directory', $report);

    $fileLabel = PdfFilename::dated(trim('GroupDirectory ' . $report['group_name']));
    return PdfResponse::create($response, $pdf, $fileLabel);
});

// Photo book: the members of each named group with their photos, or every Sunday School class when none is named.
$app->get('/reports/photo-book', function (Request $request, Response $response) {
    $groupIds = array_values(array_filter(array_map('intval', explode(',', (string) ($request->getQueryParams()['GroupID'] ?? '')))));
    if ($groupIds === []) {
        $groupIds = array_map('intval', GroupQuery::create()->filterByType(4)->orderByName()->find()->getPrimaryKeys(false));
    }

    $pdf = (new MpdfRenderer())->renderToString('photo-book', [
        'sections' => PhotoBookService::sections($groupIds),
    ]);
    $groupName = count($groupIds) === 1 ? (string) GroupQuery::create()->findOneById($groupIds[0])?->getName() : '';
    $fileLabel = PdfFilename::dated(trim('PhotoBook ' . $groupName));
    return PdfResponse::create($response, $pdf, $fileLabel);
});
