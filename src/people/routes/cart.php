<?php

/**
 * People module — Cart routes
 *
 * GET  /people/cart/to-family  Render the assign-to-family form (or empty state)
 * POST /people/cart/to-family  Validate, create/select family, assign cart persons, redirect
 *
 * Related: #9229 (this migration), #9227 (sibling /v2/cart -> /people/cart migration)
 */

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/cart', function (RouteCollectorProxy $group): void {

    // -----------------------------------------------------------------------
    // GET /people/cart/to-family — render form (or empty state)
    // -----------------------------------------------------------------------
    $group->get('/to-family', function (Request $request, Response $response, array $args): Response {
        AuthenticationManager::redirectHomeIfFalse(
            AuthenticationManager::getCurrentUser()->isAddRecordsEnabled(),
            'AddRecords'
        );

        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $cartPersons = Cart::getCartPeople();
        $familyRoles = ListOptionQuery::create()
            ->filterByListId(2)
            ->orderByOptionSequence()
            ->find();
        $families = FamilyQuery::create()->orderByName()->find();

        $pageArgs = [
            'sPageTitle'    => gettext('Add to Family'),
            'sPageSubtitle' => gettext('Assign people from your cart to a family'),
            'aBreadcrumbs'  => PageHeader::breadcrumbs([
                [gettext('People'), '/people/dashboard'],
                [gettext('Cart'), '/people/cart'],
                [gettext('Add to Family')],
            ]),
            'cartPersons'   => $cartPersons,
            'familyRoles'   => $familyRoles,
            'families'      => $families,
            'sErrorText'    => '',
            'sSuccessText'  => '',
            'formValues'    => [],
        ];
        return $renderer->render($response, 'cart/to-family.php', $pageArgs);
    });

    // -----------------------------------------------------------------------
    // POST /people/cart/to-family — process form
    // -----------------------------------------------------------------------
    $group->post('/to-family', function (Request $request, Response $response, array $args): Response {
        AuthenticationManager::redirectHomeIfFalse(
            AuthenticationManager::getCurrentUser()->isAddRecordsEnabled(),
            'AddRecords'
        );

        $body = (array) $request->getParsedBody();
        $familyId = (int) ($body['FamilyID'] ?? 0);

        // If the cart is empty, bounce back to the form (shows empty state)
        if (!Cart::hasPeople()) {
            return SlimUtils::renderRedirect(
                $response,
                SystemURLs::getRootPath() . '/people/cart/to-family'
            );
        }

        $cartPersons = Cart::getCartPeople();

        // Collect roles for eligible (no existing family) persons only
        $errors = [];
        $roleByPersonId = [];

        foreach ($cartPersons as $person) {
            if ($person->getFamId() !== 0) {
                continue; // already assigned — skip silently (fixes B3)
            }
            $roleId = (int) ($body['role' . $person->getId()] ?? 0);
            if ($roleId === 0) {
                $errors[] = sprintf(
                    gettext('Please select a family role for %s.'),
                    $person->getFullName()
                );
            } else {
                $roleByPersonId[$person->getId()] = $roleId;
            }
        }

        // Guard: all cart persons already have a family — nothing to assign (fixes F2)
        if (empty($errors) && empty($roleByPersonId)) {
            $errors[] = gettext('All people in your cart are already in a family. No one can be assigned.');
        }

        // Validate new-family fields if creating (validate-before-write: fixes B2)
        // Gate on empty($errors) so the all-already-assigned message is not accompanied
        // by a spurious "family name required" error (fixes NEW-3)
        if (empty($errors) && $familyId === 0 && empty(trim($body['FamilyName'] ?? ''))) {
            $errors[] = gettext('Family name is required when creating a new family.');
        }

        // Guard: verify the user-supplied FamilyID refers to a real row (fixes F4).
        // Without this check a crafted POST can pass any integer; on MyISAM / with FK
        // enforcement disabled that silently sets per_fam_ID to a phantom value and
        // orphans the affected persons from all family views.
        if (empty($errors) && $familyId !== 0 && FamilyQuery::create()->findPk($familyId) === null) {
            $errors[] = gettext('The selected family no longer exists. Please refresh the page and try again.');
        }

        // Validation failures: re-render with errors and sticky values (no DB writes)
        if (!empty($errors)) {
            $renderer = new PhpRenderer(__DIR__ . '/../views/');
            $familyRoles = ListOptionQuery::create()
                ->filterByListId(2)
                ->orderByOptionSequence()
                ->find();
            $families = FamilyQuery::create()->orderByName()->find();

            $pageArgs = [
                'sPageTitle'    => gettext('Add to Family'),
                'sPageSubtitle' => gettext('Assign people from your cart to a family'),
                'aBreadcrumbs'  => PageHeader::breadcrumbs([
                    [gettext('People'), '/people/dashboard'],
                    [gettext('Cart'), '/people/cart'],
                    [gettext('Add to Family')],
                ]),
                'cartPersons'   => $cartPersons,
                'familyRoles'   => $familyRoles,
                'families'      => $families,
                'sErrorText'    => implode('<br>', array_map([InputUtils::class, 'escapeHTML'], $errors)),
                'sSuccessText'  => '',
                'formValues'    => $body,
            ];
            return $renderer->render($response, 'cart/to-family.php', $pageArgs);
        }

        // Create new family if requested, then assign — all in a single outer transaction
        // so a failure in emptyToFamily cannot leave an orphan family row (fixes F3).
        // emptyToFamily() participates in this transaction (no nested begin/commit).
        // Geocoding happens AFTER the commit to avoid holding the DB connection open
        // during a network call.
        $familyService = new FamilyService();
        $newFamily = null; // tracks a newly created family that needs geocoding post-commit
        $con = Propel::getConnection();
        $con->beginTransaction();
        try {
            if ($familyId === 0) {
                $newFamily = $familyService->createFamilyFromCartInput(
                    $body,
                    AuthenticationManager::getCurrentUser()->getId(),
                    $con
                );
                $familyId = $newFamily->getId();
            }

            // Assign eligible cart persons — participates in the outer transaction
            // via the passed $con (no nested begin/commit inside emptyToFamily).
            $count = Cart::emptyToFamily($familyId, $roleByPersonId, $con);
            $con->commit();
            // Cart is cleared only after a successful commit so a rollback leaves it intact
            $_SESSION['aPeopleCart'] = [];
        } catch (\Throwable $e) {
            $con->rollBack();
            LoggerUtils::getAppLogger()->error(
                'cart-to-family: unexpected error during family assignment',
                ['exception' => $e, 'familyId' => $familyId]
            );
            // Re-render the form with a user-facing error (fixes F6)
            $renderer = new PhpRenderer(__DIR__ . '/../views/');
            $familyRoles = ListOptionQuery::create()
                ->filterByListId(2)
                ->orderByOptionSequence()
                ->find();
            $families = FamilyQuery::create()->orderByName()->find();
            $pageArgs = [
                'sPageTitle'    => gettext('Add to Family'),
                'sPageSubtitle' => gettext('Assign people from your cart to a family'),
                'aBreadcrumbs'  => PageHeader::breadcrumbs([
                    [gettext('People'), '/people/dashboard'],
                    [gettext('Cart'), '/people/cart'],
                    [gettext('Add to Family')],
                ]),
                'cartPersons'   => $cartPersons,
                'familyRoles'   => $familyRoles,
                'families'      => $families,
                'sErrorText'    => InputUtils::escapeHTML(gettext('An unexpected error occurred. Please try again.')),
                'sSuccessText'  => '',
                'formValues'    => $body,
            ];
            return $renderer->render($response, 'cart/to-family.php', $pageArgs);
        }

        // Geocode the new family *after* committing — keeps the DB connection
        // free during the external network call and ensures the family row is
        // fully visible to the default connection used by autoGeocodeFamily.
        if ($newFamily !== null && (!empty($body['Address1']) || !empty($body['City']))) {
            $familyService->autoGeocodeFamily($newFamily);
        }

        $_SESSION['sGlobalMessage'] = sprintf(
            ngettext(
                '%d person successfully added to selected family.',
                '%d people successfully added to selected family.',
                $count
            ),
            $count
        );
        $_SESSION['sGlobalMessageClass'] = 'success';

        return SlimUtils::renderRedirect(
            $response,
            SystemURLs::getRootPath() . '/people/family/' . $familyId
        );
    })->add(new CSRFMiddleware('cart_to_family'));
});
