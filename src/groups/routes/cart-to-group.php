<?php

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /groups/cart-to-group — display the cart-to-group assignment page.
// Also handles the one-action "Create Group + ADD Cart" flow:
// when ?groupeCreationID=<id> is present and the cart is non-empty the
// cart is immediately emptied into the newly-created group (role 0 ->
// default role), a flash message is set, and the user is redirected to
// the group view.
//
// Auth: ManageGroupRoleAuthMiddleware is registered globally for all
// /groups/* routes in src/groups/index.php via MvcAppFactory::create().
// It enforces isManageGroupsEnabled() and returns HTTP 403 before any
// closure runs when the user lacks the permission.
$app->get('/cart-to-group', function (Request $request, Response $response) {
    $params = $request->getQueryParams();

    // One-action create-group-and-add-cart flow (legacy compatibility).
    if (!empty($params['groupeCreationID']) && !empty($_SESSION['aPeopleCart'])) {
        $iGroupID = (int) $params['groupeCreationID'];

        // Validate that the group exists before operating on the cart.
        $group = GroupQuery::create()->findPk($iGroupID);
        if ($group !== null) {
            $iCount = count($_SESSION['aPeopleCart']);
            Cart::emptyToGroup($iGroupID, 0);

            $_SESSION['sGlobalMessage']      = sprintf(
                ngettext(
                    '%d Person successfully added to selected Group.',
                    '%d People successfully added to selected Group.',
                    $iCount
                ),
                $iCount
            );
            $_SESSION['sGlobalMessageClass'] = 'success';

            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/groups/view/' . $iGroupID)
                ->withStatus(302);
        } else {
            // Group no longer exists (deleted between creation and redirect).
            $_SESSION['sGlobalMessage']      = gettext('The group could not be found. Please try again.');
            $_SESSION['sGlobalMessageClass'] = 'danger';
        }
    }

    // Collect cart people.
    $aPeopleInCart = [];
    $cartCount     = 0;
    if (!empty($_SESSION['aPeopleCart'])) {
        $aPeopleInCart = PersonQuery::create()
            ->filterById($_SESSION['aPeopleCart'])
            ->find();
        $cartCount = count($aPeopleInCart);
    }

    $aGroups  = GroupQuery::create()->orderByName()->find();

    // Read and clear any flash message set by a prior POST redirect
    // (e.g. empty-cart warning, group-not-found error).
    // Escape for safe interpolation into the JS string in Include/Footer.php.
    $sGlobalMessage      = '';
    $sGlobalMessageClass = 'success';
    if (isset($_SESSION['sGlobalMessage'])) {
        $sGlobalMessage      = $_SESSION['sGlobalMessage'];
        $sGlobalMessageClass = $_SESSION['sGlobalMessageClass'] ?? 'success';
        unset($_SESSION['sGlobalMessage'], $_SESSION['sGlobalMessageClass']);
        $flags               = JSON_INVALID_UTF8_SUBSTITUTE;
        $sGlobalMessage      = substr(json_encode($sGlobalMessage,      $flags), 1, -1);
        $sGlobalMessageClass = substr(json_encode($sGlobalMessageClass, $flags), 1, -1);
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'cart-to-group.php', [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('Add Cart to Group'),
        'sPageSubtitle'      => gettext('Assign cart members to a group'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Groups'), '/groups/dashboard'],
            [gettext('Add Cart to Group')],
        ]),
        'aPeopleInCart'      => $aPeopleInCart,
        'cartCount'          => $cartCount,
        'aGroups'            => $aGroups,
        'sGlobalMessage'     => $sGlobalMessage,
        'sGlobalMessageClass' => $sGlobalMessageClass,
    ]);
});

// POST /groups/cart-to-group — process form submission.
// Guards on GroupID only (not on the submit button value) so that the
// handler works correctly regardless of how the button value is serialised
// by the browser.
//
// Auth: same ManageGroupRoleAuthMiddleware guard as the GET handler above.
$app->post('/cart-to-group', function (Request $request, Response $response) {
    $body     = $request->getParsedBody();
    $iGroupID = !empty($body['GroupID']) ? (int) $body['GroupID'] : 0;

    if ($iGroupID > 0 && !empty($_SESSION['aPeopleCart'])) {
        // Validate group exists before emptying the cart.
        $group = GroupQuery::create()->findPk($iGroupID);
        if ($group !== null) {
            $iGroupRole = isset($body['GroupRole']) ? (int) $body['GroupRole'] : 0;
            $iCount     = count($_SESSION['aPeopleCart']);

            Cart::emptyToGroup($iGroupID, $iGroupRole);

            $_SESSION['sGlobalMessage']      = sprintf(
                ngettext(
                    '%d Person successfully added to selected Group.',
                    '%d People successfully added to selected Group.',
                    $iCount
                ),
                $iCount
            );
            $_SESSION['sGlobalMessageClass'] = 'success';

            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/groups/view/' . $iGroupID)
                ->withStatus(302);
        }
    }

    // Fall back — redirect to the form with a contextual error message.
    if ($iGroupID > 0) {
        if (empty($_SESSION['aPeopleCart'])) {
            // Cart was emptied between page load and submit.
            $_SESSION['sGlobalMessage']      = gettext('Your cart is empty. Add people to your cart first.');
            $_SESSION['sGlobalMessageClass'] = 'warning';
        } else {
            // GroupID supplied but the group was not found (deleted since page load).
            $_SESSION['sGlobalMessage']      = gettext('The selected group was not found. Please select a valid group.');
            $_SESSION['sGlobalMessageClass'] = 'danger';
        }
    }
    // No message for $iGroupID === 0: the browser-side 'required' attribute
    // prevents submission without a selection; no server-side flash needed.
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/groups/cart-to-group')
        ->withStatus(302);
});
