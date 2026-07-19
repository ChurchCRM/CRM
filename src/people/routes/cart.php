<?php

/**
 * People module — Cart routes
 *
 * GET  /people/cart/to-family  Render the assign-to-family form (or empty state)
 * POST /people/cart/to-family  Validate, create/select family, assign cart persons, redirect
 *
 * Related: #9229 (this migration), #9227 (sibling /v2/cart → /people/cart migration)
 */

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\view\PageHeader;
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

        // Validate new-family fields if creating (validate-before-write: fixes B2)
        if ($familyId === 0 && empty(trim($body['FamilyName'] ?? ''))) {
            $errors[] = gettext('Family name is required when creating a new family.');
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
                'sErrorText'    => implode('<br>', array_map('htmlspecialchars', $errors)),
                'sSuccessText'  => '',
                'formValues'    => $body,
            ];
            return $renderer->render($response, 'cart/to-family.php', $pageArgs);
        }

        // Create new family if requested (validate-before-write ensures name is present)
        if ($familyId === 0) {
            $familyService = new FamilyService();
            $family = $familyService->createFamilyFromCartInput(
                $body,
                AuthenticationManager::getCurrentUser()->getId()
            );
            $familyId = $family->getId();
        }

        // Assign eligible cart persons to the family — transactional (fixes B2/B3/B11)
        $count = Cart::emptyToFamily($familyId, $roleByPersonId);

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
