<?php

/**
 * Signup Sheets — unauthenticated routes.
 *
 * Mounted on the /external Slim app, which has no AuthMiddleware. Every route
 * here is reachable by anonymous visitors, so authorization is entirely by
 * unguessable token:
 *
 *   /external/signup-sheets/{sheetToken}          — the sheet, if published
 *   /external/signup-sheets/{sheetToken}/claim    — claim a slot
 *   /external/signup-sheets/manage/{claimToken}   — the volunteer's own signup
 *
 * Public pages never reveal other volunteers' contact details, and claims are
 * rate limited per hashed IP address.
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugins\SignupSheets\SignupSheetService;
use ChurchCRM\Plugins\SignupSheets\SignupSheetsPlugin;
use ChurchCRM\Plugins\SignupSheets\SignupValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$signupSheetsPublicPlugin = SignupSheetsPlugin::getInstance();

if ($signupSheetsPublicPlugin === null) {
    return;
}

$signupSheetsPublicViews = __DIR__ . '/../views/public/';

/**
 * Render the shared "this link doesn't work" page.
 */
$signupSheetsNotFound = static function (Response $response, string $message) use ($signupSheetsPublicViews): Response {
    $renderer = new PhpRenderer($signupSheetsPublicViews);

    return $renderer->render($response->withStatus(404), 'unavailable.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Signup Unavailable'),
        'message' => $message,
    ]);
};

/**
 * Best-effort client address for rate limiting. Only ever stored hashed.
 */
$signupSheetsClientIp = static function (Request $request): string {
    $params = $request->getServerParams();

    return (string) ($params['REMOTE_ADDR'] ?? '');
};

$app->group('/signup-sheets', function (RouteCollectorProxy $group) use (
    $signupSheetsPublicPlugin,
    $signupSheetsPublicViews,
    $signupSheetsNotFound,
    $signupSheetsClientIp
): void {
    // -------------------------------------------------------------------
    // A volunteer's own signup, reachable from the link they were given
    // -------------------------------------------------------------------
    $group->get('/manage/{claimToken}', function (Request $request, Response $response, array $args) use (
        $signupSheetsPublicPlugin,
        $signupSheetsPublicViews,
        $signupSheetsNotFound
    ): Response {
        $service = $signupSheetsPublicPlugin->getService();
        $claim = $service->findClaimByManageToken((string) $args['claimToken']);

        if ($claim === null || !$signupSheetsPublicPlugin->isPublicSharingAllowed()) {
            return $signupSheetsNotFound($response, gettext('This signup link is no longer valid.'));
        }

        $renderer = new PhpRenderer($signupSheetsPublicViews);

        return $renderer->render($response, 'manage.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Your Signup'),
            'claim' => $claim,
            'cancelled' => false,
        ]);
    });

    $group->post('/manage/{claimToken}/cancel', function (Request $request, Response $response, array $args) use (
        $signupSheetsPublicPlugin,
        $signupSheetsPublicViews,
        $signupSheetsNotFound
    ): Response {
        $service = $signupSheetsPublicPlugin->getService();
        $claim = $service->findClaimByManageToken((string) $args['claimToken']);

        if ($claim === null || !$signupSheetsPublicPlugin->isPublicSharingAllowed()) {
            return $signupSheetsNotFound($response, gettext('This signup link is no longer valid.'));
        }

        $service->deleteClaim((int) $claim['sgc_ID']);
        $service->audit(SignupSheetService::EVENT_CANCEL, (int) $claim['sheet_id'], null);

        $renderer = new PhpRenderer($signupSheetsPublicViews);

        return $renderer->render($response, 'manage.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Signup Cancelled'),
            'claim' => $claim,
            'cancelled' => true,
        ]);
    });

    // -------------------------------------------------------------------
    // The public sheet
    // -------------------------------------------------------------------
    $group->get('/{sheetToken}', function (Request $request, Response $response, array $args) use (
        $signupSheetsPublicPlugin,
        $signupSheetsPublicViews,
        $signupSheetsNotFound
    ): Response {
        $service = $signupSheetsPublicPlugin->getService();

        if (!$signupSheetsPublicPlugin->isPublicSharingAllowed()) {
            return $signupSheetsNotFound($response, gettext('Public signup links are turned off for this church.'));
        }

        $sheet = $service->findPublicSheet((string) $args['sheetToken']);
        if ($sheet === null) {
            return $signupSheetsNotFound($response, gettext('This signup sheet could not be found. The link may have expired.'));
        }

        $renderer = new PhpRenderer($signupSheetsPublicViews);

        return $renderer->render($response, 'sheet.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => (string) $sheet['shs_title'],
            'sheet' => $sheet,
            'slotsByCategory' => $service->getSlotsByCategory($sheet),
            'claimsBySlot' => $service->getClaimsBySlot((int) $sheet['shs_ID']),
            'isAccepting' => $service->isAcceptingSignups($sheet),
            'contactEmail' => $signupSheetsPublicPlugin->getContactEmail(),
            'sheetToken' => (string) $sheet['shs_public_token'],
            'errorMessage' => null,
            'confirmation' => null,
        ]);
    });

    // -------------------------------------------------------------------
    // Claiming a slot
    // -------------------------------------------------------------------
    $group->post('/{sheetToken}/claim', function (Request $request, Response $response, array $args) use (
        $signupSheetsPublicPlugin,
        $signupSheetsPublicViews,
        $signupSheetsNotFound,
        $signupSheetsClientIp
    ): Response {
        $service = $signupSheetsPublicPlugin->getService();

        if (!$signupSheetsPublicPlugin->isPublicSharingAllowed()) {
            return $signupSheetsNotFound($response, gettext('Public signup links are turned off for this church.'));
        }

        $sheet = $service->findPublicSheet((string) $args['sheetToken']);
        if ($sheet === null) {
            return $signupSheetsNotFound($response, gettext('This signup sheet could not be found. The link may have expired.'));
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $ipHash = $service->hashIp($signupSheetsClientIp($request));
        $sheetId = (int) $sheet['shs_ID'];

        $renderSheet = function (?string $errorMessage, ?array $confirmation) use (
            $response,
            $signupSheetsPublicViews,
            $service,
            $sheet,
            $signupSheetsPublicPlugin
        ): Response {
            $renderer = new PhpRenderer($signupSheetsPublicViews);

            return $renderer->render($response, 'sheet.php', [
                'sRootPath' => SystemURLs::getRootPath(),
                'sPageTitle' => (string) $sheet['shs_title'],
                'sheet' => $sheet,
                'slotsByCategory' => $service->getSlotsByCategory($sheet),
                'claimsBySlot' => $service->getClaimsBySlot((int) $sheet['shs_ID']),
                'isAccepting' => $service->isAcceptingSignups($sheet),
                'contactEmail' => $signupSheetsPublicPlugin->getContactEmail(),
                'sheetToken' => (string) $sheet['shs_public_token'],
                'errorMessage' => $errorMessage,
                'confirmation' => $confirmation,
            ]);
        };

        // Honeypot: a field real people never see and never fill in.
        if (!empty($body['website'])) {
            $service->audit(SignupSheetService::EVENT_REJECTED_HONEYPOT, $sheetId, $ipHash);

            return $renderSheet(gettext('Your signup could not be accepted.'), null);
        }

        // Two limits, counted separately. The attempt limit throttles abusive
        // traffic of any shape; the signup limit is the business rule about how
        // many slots one visitor may take. Auditing the claim only after it is
        // accepted is what keeps rejected submissions out of the second count —
        // otherwise anyone with the link could burn a shared address's signup
        // allowance with submissions that were never going to be accepted.
        if ($service->isAttemptLimitReached($ipHash, $signupSheetsPublicPlugin->getPublicAttemptLimit())) {
            $service->audit(SignupSheetService::EVENT_RATE_LIMITED, $sheetId, $ipHash);

            return $renderSheet(gettext('Too many requests from this connection in the last hour. Please try again later.'), null);
        }

        if ($service->isClaimLimitReached($ipHash, $signupSheetsPublicPlugin->getPublicRateLimit())) {
            $service->audit(SignupSheetService::EVENT_RATE_LIMITED, $sheetId, $ipHash);

            return $renderSheet(gettext('Too many signups from this connection in the last hour. Please try again later.'), null);
        }

        try {
            $result = $service->claimSlot(
                $sheet,
                (int) ($body['slotId'] ?? 0),
                $body,
                SignupSheetService::SOURCE_PUBLIC,
                null
            );
        } catch (SignupValidationException $e) {
            $service->audit(SignupSheetService::EVENT_REJECTED_INVALID, $sheetId, $ipHash);

            return $renderSheet($e->getMessage(), null);
        }

        $service->audit(SignupSheetService::EVENT_CLAIM, $sheetId, $ipHash);

        return $renderSheet(null, [
            'name' => (string) ($body['name'] ?? ''),
            'manageUrl' => SystemURLs::getURL() . '/external/signup-sheets/manage/' . $result['manageToken'],
        ]);
    });
});
