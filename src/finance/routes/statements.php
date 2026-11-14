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
        'statementTax' => [gettext('Tax Information'), 'fa-solid fa-building-columns', [
            'sChurchTaxId',
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
        'sChurchTaxId'   => gettext('Church Tax ID (EIN)'),
        'iPDFOutputType' => gettext('PDF Handling'),
    ];

    $sections = [];
    foreach ($sectionDefinitions as $id => [$title, $icon, $keys]) {
        $settings = array_map(function (array $setting) use ($labels): array {
            $setting['label'] = $labels[$setting['name']] ?? $setting['label'];
            if (($setting['tooltip'] ?? '') === $setting['label']) {
                unset($setting['tooltip']);
            }

            if (in_array($setting['name'], ['sGivingStatementOpening', 'sGivingStatementClosing', 'sGivingStatementQuestions'], true)) {
                $setting['type'] = 'textarea';
            }

            return $setting;
        }, SystemConfig::getSettingsConfig($keys));

        $sections[] = ['id' => $id, 'title' => $title, 'icon' => $icon, 'settings' => $settings];
    }

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Statement Settings'),
        'sPageSubtitle' => gettext('Wording, tax ID and PDF handling for giving statements'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Finance'), '/finance/'],
            [gettext('Statement Settings')],
        ]),
        'sections'     => $sections,
    ];

    return $renderer->render($response, 'statements.php', $pageArgs);
});
