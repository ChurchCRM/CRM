<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/people', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $sectionDefinitions = [
        'peoplePeople' => [gettext('People'), 'fa-solid fa-user', [
            'iPersonNameStyle', 'iPersonInitialStyle', 'bHidePersonAddress', 'bHideFriendDate', 'bHideDeceasedFromDirectory',
        ]],
        'peopleFamilies' => [gettext('Families'), 'fa-solid fa-people-roof', [
            'sDirRoleHead', 'sDirRoleSpouse', 'sDirRoleChild', 'bHideWeddingDate', 'bHideFamilyNewsletter', 'bForceUppercaseZip',
        ]],
        'peopleNewMembers' => [gettext('New Members & Greeting'), 'fa-solid fa-user-plus', [
            'bEnableSelfRegistration', 'sNewPersonNotificationRecipientIDs', 'IncludeDataInNewPersonNotifications', 'sGreeterCustomMsg1', 'sGreeterCustomMsg2',
        ]],
        'peopleDefaults' => [gettext('New Record Defaults'), 'fa-solid fa-address-card', [
            'sDefaultCountry', 'sDefaultState', 'sDefaultCity', 'sDefaultZip',
        ]],
        'peopleMap' => [gettext('Map Settings'), 'fa-solid fa-map', [
            'iMapZoom', 'bHideLatLon',
        ]],
    ];

    // Short labels; the legacy sentence stays as the help text.
    $labels = [
        'bEnableSelfRegistration'              => gettext('Self-Registration'),
        'bHidePersonAddress'                   => gettext('Hide Address Without Family'),
        'bHideFriendDate'                      => gettext('Hide Friend Date'),
        'bHideWeddingDate'                     => gettext('Hide Wedding Date'),
        'bForceUppercaseZip'                   => gettext('Uppercase Zip/Postcodes'),
        'bHideDeceasedFromDirectory'           => gettext('Hide Deceased from Directory'),
        'sDirRoleHead'                         => gettext('Head of House Role'),
        'sDirRoleSpouse'                       => gettext('Spouse Role'),
        'sDirRoleChild'                        => gettext('Child Role'),
        'bHideFamilyNewsletter'                => gettext('Hide Newsletter Subscriptions'),
        'sNewPersonNotificationRecipientIDs'   => gettext('Notification Recipients'),
        'IncludeDataInNewPersonNotifications'  => gettext('Include Details in Notifications'),
        'sGreeterCustomMsg1'                   => gettext('Greeter Message 1'),
        'sGreeterCustomMsg2'                   => gettext('Greeter Message 2'),
        'sDefaultCountry'                      => gettext('Default Country'),
        'sDefaultState'                        => gettext('Default State'),
        'sDefaultCity'                         => gettext('Default City'),
        'sDefaultZip'                          => gettext('Default Zip'),
        'iMapZoom'                             => gettext('Default Map View'),
        'bHideLatLon'                          => gettext('Hide Latitude/Longitude'),
    ];

    $sections = [];
    foreach ($sectionDefinitions as $id => [$title, $icon, $keys]) {
        $settings = array_map(function (array $setting) use ($labels): array {
            $setting['label'] = $labels[$setting['name']] ?? $setting['label'];
            if ($setting['tooltip'] === $setting['label']) {
                unset($setting['tooltip']);
            }

            if ($setting['name'] === 'sDefaultCountry') {
                $setting['tooltip'] = gettext('Used for new records and for geocoding when a record has no country.');
            }

            if (in_array($setting['name'], ['sGreeterCustomMsg1', 'sGreeterCustomMsg2'], true)) {
                $setting['type'] = 'textarea';
            }
            if ($setting['name'] === 'sNewPersonNotificationRecipientIDs') {
                $ids = array_filter(explode(',', SystemConfig::getValue($setting['name'])), 'is_numeric');
                $setting['type'] = 'persons';
                $setting['selected'] = array_map(
                    fn ($person) => ['id' => $person->getId(), 'text' => $person->getFullName()],
                    iterator_to_array(PersonQuery::create()->filterById($ids)->find()),
                );
            }

            return $setting;
        }, SystemConfig::getSettingsConfig($keys));

        $sections[] = ['id' => $id, 'title' => $title, 'icon' => $icon, 'settings' => $settings];
    }

    $pageArgs = [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('People Settings'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('People Settings')],
        ]),
        'sections'      => $sections,
        'listLinks'     => [
            [gettext('Person Classifications'), '/admin/system/options?mode=classes', 'fa-tags'],
            [gettext('Person Properties'), '/PropertyList.php?Type=p', 'fa-person-half-dress'],
            [gettext('Person Custom Fields'), '/PersonCustomFieldsEditor.php', 'fa-sliders'],
            [gettext('Family Roles'), '/admin/system/options?mode=famroles', 'fa-people-roof'],
            [gettext('Family Properties'), '/PropertyList.php?Type=f', 'fa-people-roof'],
            [gettext('Family Custom Fields'), '/FamilyCustomFieldsEditor.php', 'fa-sliders'],
            [gettext('Volunteer Opportunities'), '/VolunteerOpportunityEditor.php', 'fa-handshake-angle'],
        ],
    ];

    return $renderer->render($response, 'people.php', $pageArgs);
});
