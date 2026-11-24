<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\QuickBooksTicketSettingsService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$financeBreadcrumbs = fn (string $page): array => PageHeader::breadcrumbs([
    [gettext('Finance Settings'), '/admin/finance'],
    [$page],
]);

$app->get('/finance', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'finance.php', [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Finance Settings'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Finance'), '/finance/'],
            [gettext('Finance Settings')],
        ]),
        'listLinks'    => [
            [gettext('Donation Funds'), '/finance/funds', 'fa-piggy-bank'],
            [gettext('Envelope Manager'), '/finance/envelopes', 'fa-envelope'],
            [gettext('Statement Settings'), '/finance/statements/settings', 'fa-file-invoice'],
            [gettext('Report Settings'), '/admin/system/report-settings', 'fa-file-lines'],
            [gettext('Pledge Reminders'), '/admin/finance/pledge-reminders', 'fa-bell'],
            [gettext('Zero Givers'), '/admin/finance/zero-givers', 'fa-user-check'],
            [gettext('QuickBooks Deposit Ticket Settings'), '/admin/finance/quickbooks', 'fa-receipt'],
            [gettext('Deposits & Payments'), '/admin/finance/options', 'fa-sliders'],
        ],
    ]);
});

$financeEditors = [
    'pledge-reminders' => ['financePledgeReminders', gettext('Pledge Reminders'), 'fa-solid fa-bell', [
        'sReminder1' => gettext('Opening Line'),
        'sReminderSigner' => gettext('Signer'),
        'sReminderNoPledge' => gettext('No Pledge Text'),
        'sReminderNoPayments' => gettext('No Payments Text'),
    ]],
    'zero-givers' => ['financeZeroGivers', gettext('Zero Givers'), 'fa-solid fa-user-check', [
        'sZeroGivers' => gettext('Text After Dates'),
        'sZeroGivers2' => gettext('Closing Line'),
        'sZeroGivers3' => gettext('Questions Line'),
    ]],
    'options' => ['financeDeposits', gettext('Deposits & Payments'), 'fa-solid fa-sliders', [
        'iFYMonth' => gettext('First month of the fiscal year'),
        'iMaxTaxYears' => gettext('Maximum tax years shown per family'),
        'sDepositSlipType' => gettext('Deposit ticket type'),
        'bDisplayBillCounts' => gettext('Display bill counts on deposit slip'),
        'bUseScannedChecks' => gettext('Enable use of scanned checks'),
        'bRequireCheckNumber' => gettext('Require check number on check payments'),
        'bEnableNonDeductible' => gettext('Enable non-deductible payments'),
        'bUseDonationEnvelopes' => gettext('Enable use of donation envelopes'),
    ]],
];
$textareaKeys = ['sReminder1', 'sReminderNoPledge', 'sReminderNoPayments', 'sZeroGivers', 'sZeroGivers2', 'sZeroGivers3'];

foreach ($financeEditors as $slug => [$panelId, $title, $icon, $labels]) {
    $app->get('/finance/' . $slug, function (Request $request, Response $response) use ($financeBreadcrumbs, $panelId, $title, $icon, $labels, $textareaKeys): Response {
        $settings = array_map(function (array $setting) use ($labels, $textareaKeys): array {
            $setting['label'] = $labels[$setting['name']];
            if (($setting['tooltip'] ?? '') === $setting['label']) {
                unset($setting['tooltip']);
            }
            if (in_array($setting['name'], $textareaKeys, true)) {
                $setting['type'] = 'textarea';
            }

            return $setting;
        }, SystemConfig::getSettingsConfig(array_keys($labels)));

        return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'finance-editor.php', [
            'sRootPath'    => SystemURLs::getRootPath(),
            'sPageTitle'   => $title,
            'aBreadcrumbs' => $financeBreadcrumbs($title),
            'panelId'      => $panelId,
            'panelTitle'   => $title,
            'panelIcon'    => $icon,
            'settings'     => $settings,
        ]);
    });
}

$app->get('/finance/quickbooks', function (Request $request, Response $response) use ($financeBreadcrumbs): Response {
    return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'finance-quickbooks.php', [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('QuickBooks Deposit Ticket Settings'),
        'sPageSubtitle' => gettext('Positions in millimetres on the preprinted deposit ticket'),
        'aBreadcrumbs' => $financeBreadcrumbs(gettext('QuickBooks Deposit Ticket Settings')),
        'values'       => (new QuickBooksTicketSettingsService())->read(),
        'csrfToken'    => CSRFUtils::generateToken(),
    ]);
});

/**
 * @OA\Post(
 *     path="/admin/api/finance/quickbooks",
 *     operationId="saveQuickBooksTicketSettings",
 *     summary="Save the QuickBooks deposit ticket positions",
 *     description="Takes all 18 positions in millimetres (0 to 1000); an incomplete set is rejected.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", example={"date1X":"12","date1Y":"42"})),
 *     @OA\Response(response=200, description="Positions saved"),
 *     @OA\Response(response=400, description="A position is missing, not a number, or out of range"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
$app->post('/api/finance/quickbooks', function (Request $request, Response $response): Response {
    try {
        $service = new QuickBooksTicketSettingsService();
        $service->save((array) $request->getParsedBody());
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, $service->read());
})->add(new CSRFMiddleware())->add(AdminRoleAuthMiddleware::class);
