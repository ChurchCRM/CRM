<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/statements/settings', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $sectionDefinitions = [
        'statementWording' => [gettext('Statement Wording'), 'fa-solid fa-file-lines', [
            'sGivingStatementOpening', 'sGivingStatementClosing', 'sGivingStatementQuestions', 'sTaxSigner',
        ]],
        'statementTax' => [gettext('Charity on receipts'), 'fa-solid fa-building-columns', [
            'sChurchTaxId', 'sCharityLegalName', 'sCharityRegisteredAddress', 'sCharityRegistrationNumber',
            'sReceiptIssueLocality', 'sReceiptClosing',
        ]],
        'statementOutput' => [gettext('PDF Output'), 'fa-solid fa-file-pdf', [
            'iPDFOutputType',
        ]],
    ];

    $labels = [
        'sGivingStatementOpening' => gettext('Opening Line'),
        'sGivingStatementClosing' => gettext('Closing Line'),
        'sGivingStatementQuestions' => gettext('Questions Line'),
        'sTaxSigner'     => gettext('Signer'),
        'sChurchTaxId'   => gettext('Tax ID on statements'),
        'sCharityLegalName' => gettext('Charity legal name'),
        'sCharityRegisteredAddress' => gettext('Charity registered address'),
        'sCharityRegistrationNumber' => gettext('Registration number'),
        'sReceiptIssueLocality' => gettext('Issue locality'),
        'sReceiptClosing' => gettext('Receipt closing'),
        'iPDFOutputType' => gettext('PDF Handling'),
    ];
    $help = [
        'sChurchTaxId' => gettext('Printed on ordinary giving statements, for example the EIN in the United States. The same value is on Church Info.'),
        'sCharityLegalName' => gettext('The name the charity is registered under. It can differ from the church name.'),
        'sCharityRegisteredAddress' => gettext('The charity\'s registered address. Blank uses the address on Church Info.'),
        'sCharityRegistrationNumber' => gettext('The charity registration number printed on official receipts. Blank uses the Tax ID on Church Info.'),
        'sReceiptIssueLocality' => gettext('The place where receipts are issued. Blank uses the city on Church Info.'),
        'sReceiptClosing' => gettext('The closing phrase above the signer, for example Sincerely.'),
    ];

    $sections = [];
    foreach ($sectionDefinitions as $id => [$title, $icon, $keys]) {
        $settings = array_map(function (array $setting) use ($labels, $help): array {
            $setting['label'] = $labels[$setting['name']] ?? $setting['label'];
            $setting['tooltip'] = $help[$setting['name']] ?? '';
            if ($setting['tooltip'] === '') {
                unset($setting['tooltip']);
            }

            if (in_array($setting['name'], ['sGivingStatementOpening', 'sGivingStatementClosing', 'sGivingStatementQuestions', 'sCharityRegisteredAddress'], true)) {
                $setting['type'] = 'textarea';
            }

            return $setting;
        }, SystemConfig::getSettingsConfig($keys));

        $sections[] = ['id' => $id, 'title' => $title, 'icon' => $icon, 'settings' => $settings];
    }

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Statement Settings'),
        'sPageSubtitle' => gettext('Letter wording, charity details, and PDF handling for giving statements'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Year-End Giving'), '/finance/reports/year-end'],
            [gettext('Statement Settings')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Giving Statements'), 'url' => '/finance/reports/giving-statements', 'icon' => 'fa-file-invoice-dollar', 'adminOnly' => false],
            ['label' => gettext('Report Settings'), 'url' => '/admin/system/report-settings', 'icon' => 'fa-signature'],
        ]),
        'sections'     => $sections,
    ];

    return $renderer->render($response, 'statements.php', $pageArgs);
});
