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
            'sReceiptIssueLocality', 'sReceiptClosing', 'bCanadianGivingStatement',
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
        'bCanadianGivingStatement' => gettext('Canadian official receipts'),
        'iPDFOutputType' => gettext('PDF Handling'),
    ];
    $help = [
        'sChurchTaxId' => gettext('Printed on ordinary giving statements. In the United States this is often the EIN. The same value is on Church Info.'),
        'sCharityRegistrationNumber' => gettext('Any registration number. Canadian official receipts require the form 123456789RR0001. Saving this does not check that form.'),
        'bCanadianGivingStatement' => gettext('Shows the Canadian receipt on Giving Statements. Ordinary statements are then marked as not an official donation receipt. The signature image is uploaded under Admin, Report Settings.'),
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
        'sections'     => $sections,
    ];

    return $renderer->render($response, 'statements.php', $pageArgs);
});
