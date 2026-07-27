<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /groups/editor/{groupID} — render the group editor.
//
// Auth: ManageGroupRoleAuthMiddleware is registered globally for all
// /groups/* routes in src/groups/index.php.  An explicit per-handler check
// is omitted because the module middleware already enforces it.
$app->get('/editor/{groupID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    $iGroupID = (int) $args['groupID'];

    if ($iGroupID < 1) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    $thisGroup = GroupQuery::create()->findOneById($iGroupID);
    if ($thisGroup === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    // Group types for the type drop-down (list option ID = 3).
    $rsGroupTypes = ListOptionQuery::create()->filterById('3')->find();

    // Groups available as role-list seeds for clone-on-create flow.
    // Fix: the original used an undefined $comparison variable; use Criteria::GREATER_THAN.
    $rsGroupRoleSeed = GroupQuery::create()->filterByRoleListId(0, Criteria::GREATER_THAN)->find();

    $groupService = new GroupService();
    $groupRoleData = $groupService->getGroupRoles($iGroupID);

    $sPageTitle    = gettext('Group Editor');
    $sPageSubtitle = gettext('Configure group settings, roles, and properties');
    $aBreadcrumbs  = PageHeader::breadcrumbs([
        [gettext('Groups'), '/groups/dashboard'],
        [InputUtils::escapeHTML($thisGroup->getName()), '/groups/view/' . $iGroupID],
        [gettext('Edit')],
    ]);
    $sPageHeaderButtons = PageHeader::buttons([
        [
            'label' => gettext('Back to Group'),
            'url'   => SystemURLs::getRootPath() . '/groups/view/' . $iGroupID,
            'icon'  => 'fa-arrow-left',
            'style' => 'outline-secondary',
        ],
    ]);

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'group-editor.php', [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => $sPageTitle,
        'sPageSubtitle'      => $sPageSubtitle,
        'aBreadcrumbs'       => $aBreadcrumbs,
        'sPageHeaderButtons' => $sPageHeaderButtons,
        'iGroupID'           => $iGroupID,
        'thisGroup'          => $thisGroup,
        'rsGroupTypes'       => $rsGroupTypes,
        'rsGroupRoleSeed'    => $rsGroupRoleSeed,
        'groupRoleData'      => $groupRoleData,
    ]);
});
