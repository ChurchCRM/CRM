<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/finance', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $sectionDefinitions = [
        'financeDeposits' => [gettext('Deposits & Payments'), 'fa-solid fa-sliders', [
            'iFYMonth', 'iMaxTaxYears', 'sDepositSlipType', 'iChecksPerDepositForm', 'bDisplayBillCounts',
            'bUseScannedChecks', 'bRequireCheckNumber', 'bEnableNonDeductible', 'bUseDonationEnvelopes',
        ]],
        'financePledgeReminders' => [gettext('Pledge Reminders'), 'fa-solid fa-bell', [
            'sReminder1', 'sReminderSigner', 'sReminderNoPledge', 'sReminderNoPayments',
        ]],
        'financeZeroGivers' => [gettext('Zero Givers'), 'fa-solid fa-user-check', [
            'sZeroGivers', 'sZeroGivers2', 'sZeroGivers3',
        ]],
        'financeQuickBooks' => [gettext('QuickBooks Deposit Ticket Settings'), 'fa-solid fa-receipt', [
            'sQBDTSettings',
        ]],
    ];

    $labels = [
        'iFYMonth'                  => gettext('First month of the fiscal year'),
        'iMaxTaxYears'              => gettext('Maximum tax years shown per family'),
        'sDepositSlipType'          => gettext('Deposit ticket type'),
        'iChecksPerDepositForm'     => gettext('Number of checks for Deposit Slip Report'),
        'bDisplayBillCounts'        => gettext('Display bill counts on deposit slip'),
        'bUseScannedChecks'         => gettext('Enable use of scanned checks'),
        'bRequireCheckNumber'       => gettext('Require check number on check payments'),
        'bUseDonationEnvelopes'     => gettext('Enable use of donation envelopes'),
        'sReminder1'                => gettext('Opening Line'),
        'sReminderSigner'           => gettext('Signer'),
        'sReminderNoPledge'         => gettext('No Pledge Text'),
        'sReminderNoPayments'       => gettext('No Payments Text'),
        'sZeroGivers'               => gettext('Opening Line'),
        'sZeroGivers2'              => gettext('Closing Line'),
        'sZeroGivers3'              => gettext('Questions Line'),
        'sQBDTSettings'             => gettext('QuickBooks Deposit Ticket Settings'),
        'bEnableNonDeductible'      => gettext('Enable non-deductible payments'),
    ];

    $textareaKeys = [
        'sReminder1',
        'sReminderNoPledge',
        'sReminderNoPayments',
        'sZeroGivers',
        'sZeroGivers2',
        'sZeroGivers3',
    ];

    $sections = [];
    foreach ($sectionDefinitions as $id => [$title, $icon, $keys]) {
        $settings = array_map(function (array $setting) use ($labels, $textareaKeys): array {
            $setting['label'] = $labels[$setting['name']] ?? $setting['label'];
            if (($setting['tooltip'] ?? '') === $setting['label']) {
                unset($setting['tooltip']);
            }
            if (in_array($setting['name'], $textareaKeys, true)) {
                $setting['type'] = 'textarea';
            }

            return $setting;
        }, SystemConfig::getSettingsConfig($keys));

        $sections[] = ['id' => $id, 'title' => $title, 'icon' => $icon, 'settings' => $settings];
    }

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Finance Settings'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Finance'), '/finance/'],
            [gettext('Finance Settings')],
        ]),
        'sections'     => $sections,
        'listLinks'    => [
            [gettext('Donation Funds'), '/finance/funds', 'fa-piggy-bank'],
            [gettext('Envelope Manager'), '/finance/envelopes', 'fa-envelope'],
            [gettext('Statement Settings'), '/finance/statements/settings', 'fa-file-invoice'],
        ],
    ];

    return $renderer->render($response, 'finance.php', $pageArgs);
});
