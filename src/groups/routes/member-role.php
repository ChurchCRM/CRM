<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// -----------------------------------------------------------------------
// GET /groups/{groupID}/members/{personID}/role — render the role-change form.
//
// Optional ?return=group query param: when set to "group" the Cancel/Submit
// buttons go back to the group view; otherwise they go to the person view.
// The param is sanitised to exactly "group" or "person" (default) to avoid
// open-redirect or XSS via the value being reflected in the form action.
// -----------------------------------------------------------------------
$app->get('/{groupID:[0-9]+}/members/{personID:[0-9]+}/role', function (Request $request, Response $response, array $args): Response {
    $iGroupID  = (int) $args['groupID'];
    $iPersonID = (int) $args['personID'];

    $person = PersonQuery::create()->findOneById($iPersonID);
    $group  = GroupQuery::create()->findOneById($iGroupID);

    if ($person === null || $group === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    $params  = $request->getQueryParams();
    $sReturn = (($params['return'] ?? '') === 'group') ? 'group' : 'person';

    $p2g2r = Person2group2roleP2g2rQuery::create()
        ->filterByPersonId($iPersonID)
        ->filterByGroupId($iGroupID)
        ->findOne();

    $grp_RoleListID = $group->getRoleListId();
    $iRoleID        = $p2g2r ? $p2g2r->getRoleId() : 0;

    $currentRole = ListOptionQuery::create()
        ->filterById($grp_RoleListID)
        ->filterByOptionId($iRoleID)
        ->findOne();
    $sRoleName = $currentRole ? $currentRole->getOptionName() : '';

    $allRoles = ListOptionQuery::create()
        ->filterById($grp_RoleListID)
        ->orderByOptionSequence()
        ->find();

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'group-member-role.php', buildMemberRolePageArgs(
        $iGroupID, $iPersonID, $group, $person, $sRoleName, $iRoleID, $allRoles, $sReturn
    ));
});

// -----------------------------------------------------------------------
// POST /groups/{groupID}/members/{personID}/role — save new role and redirect.
// -----------------------------------------------------------------------
$app->post('/{groupID:[0-9]+}/members/{personID:[0-9]+}/role', function (Request $request, Response $response, array $args): Response {
    $iGroupID  = (int) $args['groupID'];
    $iPersonID = (int) $args['personID'];

    $person = PersonQuery::create()->findOneById($iPersonID);
    $group  = GroupQuery::create()->findOneById($iGroupID);

    if ($person === null || $group === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    $body    = (array) $request->getParsedBody();
    $iNewRole = (int) ($body['NewRole'] ?? 0);
    // Sanitise return flag from hidden form field.
    $sReturn = (($body['return'] ?? '') === 'group') ? 'group' : 'person';

    $p2g2r = Person2group2roleP2g2rQuery::create()
        ->filterByPersonId($iPersonID)
        ->filterByGroupId($iGroupID)
        ->findOne();

    if ($p2g2r !== null) {
        $p2g2r->setRoleId($iNewRole);
        $p2g2r->save();
    } else {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    if ($sReturn === 'group') {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/view/' . $iGroupID);
    }

    return SlimUtils::renderRedirect($response, Person::getViewURIForId($iPersonID));
});

// -----------------------------------------------------------------------
// Helper: build $pageArgs for the member-role view.
// -----------------------------------------------------------------------
function buildMemberRolePageArgs(
    int $iGroupID,
    int $iPersonID,
    object $group,
    object $person,
    string $sRoleName,
    int $iRoleID,
    object $allRoles,
    string $sReturn
): array {
    $aBreadcrumbs = PageHeader::breadcrumbs([
        [gettext('Groups'), '/groups/dashboard'],
        [InputUtils::escapeHTML($group->getName()), '/groups/view/' . $iGroupID],
        [gettext('Member Role Change')],
    ]);

    return [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Member Role Change'),
        'sPageSubtitle' => gettext('Modify the role for this group member'),
        'aBreadcrumbs' => $aBreadcrumbs,
        'iGroupID'     => $iGroupID,
        'iPersonID'    => $iPersonID,
        'group'        => $group,
        'person'       => $person,
        'sRoleName'    => $sRoleName,
        'iRoleID'      => $iRoleID,
        'allRoles'     => $allRoles,
        'sReturn'      => $sReturn,
    ];
}
