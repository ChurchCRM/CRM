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
        'financeTaxStatements' => [gettext('Giving Statements'), 'fa-solid fa-file-invoice-dollar', [
            'sGivingStatementOpening', 'sGivingStatementClosing', 'sGivingStatementQuestions', 'sTaxSigner',
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
        'financeOptions' => [gettext('Finance Features'), 'fa-solid fa-sliders', [
            'bEnableNonDeductible',
        ]],
    ];

    $labels = [
        'sGivingStatementOpening'   => gettext('Opening Line'),
        'sGivingStatementClosing'   => gettext('Closing Line'),
        'sGivingStatementQuestions' => gettext('Questions Line'),
        'sTaxSigner'                => gettext('Signer'),
        'sReminder1'                => gettext('Opening Line'),
        'sReminderSigner'           => gettext('Signer'),
        'sReminderNoPledge'         => gettext('No Pledge Text'),
        'sReminderNoPayments'       => gettext('No Payments Text'),
        'sZeroGivers'               => gettext('Opening Line'),
        'sZeroGivers2'              => gettext('Closing Line'),
        'sZeroGivers3'              => gettext('Questions Line'),
        'sQBDTSettings'             => gettext('QuickBooks Deposit Ticket Settings'),
        'bEnableNonDeductible'      => gettext('Non-Deductible Payments'),
    ];

    $textareaKeys = [
        'sGivingStatementOpening',
        'sGivingStatementClosing',
        'sGivingStatementQuestions',
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
