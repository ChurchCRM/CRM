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
            'sTaxReport1', 'sTaxReport2', 'sTaxReport3', 'sTaxSigner',
        ]],
        'statementTax' => [gettext('Tax Information'), 'fa-solid fa-building-columns', [
            'sChurchTaxId',
        ]],
        'statementOutput' => [gettext('PDF Output'), 'fa-solid fa-file-pdf', [
            'iPDFOutputType',
        ]],
    ];

    $labels = [
        'sTaxReport1'    => gettext('Opening Line'),
        'sTaxReport2'    => gettext('Closing Line'),
        'sTaxReport3'    => gettext('Questions Line'),
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

            if (in_array($setting['name'], ['sTaxReport1', 'sTaxReport2', 'sTaxReport3'], true)) {
                $setting['type'] = 'textarea';
            }

            return $setting;
        }, SystemConfig::getSettingsConfig($keys));

        $sections[] = ['id' => $id, 'title' => $title, 'icon' => $icon, 'settings' => $settings];
    }

    $pageArgs = [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Contribution Statement Settings'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Finance'), '/finance/'],
            [gettext('Statement Settings')],
        ]),
        'sections'     => $sections,
    ];

    return $renderer->render($response, 'statements.php', $pageArgs);
});
