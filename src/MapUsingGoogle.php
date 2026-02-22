<?php
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$sPageTitle = gettext('View on Map');

require_once __DIR__ . '/Include/Header.php';

$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');

// Colour palette for classification markers (index 0 = unassigned)
$markerColors = [
    '#dc3545', // 0  unassigned — red
    '#0d6efd', // 1  — blue
    '#198754', // 2  — green
    '#fd7e14', // 3  — orange
    '#6f42c1', // 4  — purple
    '#0dcaf0', // 5  — cyan
    '#ffc107', // 6  — yellow
    '#d63384', // 7  — pink
    '#20c997', // 8  — teal
    '#6c757d', // 9  — grey
];

function classificationColor(array $colors, int $id): string {
    return $colors[$id % count($colors)] ?? '#6c757d';
}
?>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.css') ?>">

<div class="alert alert-info">
    <a href="<?= SystemURLs::getRootPath() ?>/UpdateAllLatLon.php" class="btn btn-secondary">
        <i class="fa-solid fa-map-marker"></i>
    </a>
    <?= gettext('Missing Families? Update Family Latitude or Longitude now.') ?>
</div>

<?php if (ChurchMetaData::getChurchLatitude() === '') { ?>
    <div class="alert alert-danger">
        <?= gettext('Unable to display map due to missing Church Latitude or Longitude. Please update the church Address in the settings menu.') ?>
    </div>
<?php } else {
    $plotFamily = false;
    $dirRoleHead = SystemConfig::getValue('sDirRoleHead');

    if ($iGroupID > 0) {
        $persons = PersonQuery::create()
            ->usePerson2group2roleP2g2rQuery()
            ->filterByGroupId($iGroupID)
            ->endUse()
            ->find();
    } elseif ($iGroupID == 0) {
        if (!empty($_SESSION['aPeopleCart'])) {
            $persons = PersonQuery::create()
                ->filterById($_SESSION['aPeopleCart'])
                ->find();
        }
    } else {
        $families = FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByLatitude(0, Criteria::NOT_EQUAL)
            ->filterByLongitude(0, Criteria::NOT_EQUAL)
            ->usePersonQuery('per')
            ->filterByFmrId($dirRoleHead)
            ->endUse()
            ->find();
        $plotFamily = true;
    }

    $icons = Classification::getAll();
    ?>

    <div class="card">
        <div id="map" class="map-div"></div>

        <!-- Desktop legend -->
        <div id="maplegend">
            <h4><?= gettext('Legend') ?></h4>
            <div class="row legendbox">
                <div class="legenditem" data-classification="0">
                    <span class="legend-dot" style="background:<?= classificationColor($markerColors, 0) ?>"></span>
                    <input type="checkbox" class="legenditem-checkbox" id="legenditem-0" checked />
                    <?= gettext('Unassigned') ?>
                </div>
                <?php foreach ($icons as $icon) { ?>
                    <div class="legenditem" data-classification="<?= $icon->getOptionId() ?>">
                        <span class="legend-dot" style="background:<?= classificationColor($markerColors, $icon->getOptionId()) ?>"></span>
                        <input type="checkbox" class="legenditem-checkbox" id="legenditem-<?= $icon->getOptionId() ?>" checked />
                        <?= $icon->getOptionName() ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Mobile legend -->
        <div id="maplegend-mobile" class="card d-block d-sm-none">
            <div class="row legendbox">
                <div class="btn bg-primary col-12"><?= gettext('Legend') ?></div>
            </div>
            <div class="row legendbox">
                <div class="col-6 legenditem" data-classification="0">
                    <span class="legend-dot" style="background:<?= classificationColor($markerColors, 0) ?>"></span>
                    <div class="legenditemtext"><?= gettext('Unassigned') ?></div>
                </div>
                <?php foreach ($icons as $icon) { ?>
                    <div class="col-6 legenditem" data-classification="<?= $icon->getOptionId() ?>">
                        <span class="legend-dot" style="background:<?= classificationColor($markerColors, $icon->getOptionId()) ?>"></span>
                        <div class="legenditemtext"><?= $icon->getOptionName() ?></div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php
    $arrPlotItems = [];
    if ($plotFamily) {
        foreach ($families as $family) {
            if ($family->hasLatitudeAndLongitude()) {
                $class = $family->getHeadPeople()[0];
                $arrPlotItems[] = [
                    'ID'             => $family->getId(),
                    'Salutation'     => $family->getSalutation(),
                    'Name'           => $family->getName(),
                    'Address'        => $family->getAddress(),
                    'Latitude'       => $family->getLatitude(),
                    'Longitude'      => $family->getLongitude(),
                    'Classification' => $class->GetClsId(),
                    'isFamily'       => true,
                ];
            }
        }
    } else {
        foreach ($persons as $member) {
            $latLng = $member->getLatLng();
            $arrPlotItems[] = [
                'ID'             => $member->getId(),
                'Salutation'     => $member->getFullName(),
                'Name'           => $member->getFullName(),
                'Address'        => $member->getAddress(),
                'Latitude'       => $latLng['Latitude'],
                'Longitude'      => $latLng['Longitude'],
                'Classification' => $member->getClsId(),
                'isFamily'       => false,
            ];
        }
    }
    ?>

    <script src="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.js') ?>"></script>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        (function () {
            var churchLat   = <?= json_encode((float) ChurchMetaData::getChurchLatitude()) ?>;
            var churchLng   = <?= json_encode((float) ChurchMetaData::getChurchLongitude()) ?>;
            var mapZoom     = <?= max(1, (int) SystemConfig::getValue('iMapZoom') ?: 10) ?>;
            var plotArray   = <?= json_encode($arrPlotItems) ?>;
            var bPlotFamily = <?= $plotFamily ? 'true' : 'false' ?>;
            var classColors = <?= json_encode(array_values($markerColors)) ?>;

            function colorFor(clsId) {
                return classColors[clsId % classColors.length] || '#6c757d';
            }

            // Initialise Leaflet map centred on the church
            var map = L.map('map').setView([churchLat, churchLng], mapZoom);

            // OpenStreetMap tile layer — free, no API key required
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Church marker
            var churchIcon = L.icon({
                iconUrl:     window.CRM.root + '/skin/icons/church.png',
                iconSize:    [32, 32],
                iconAnchor:  [16, 32],
                popupAnchor: [0, -34]
            });
            L.marker([churchLat, churchLng], { icon: churchIcon })
                .bindPopup('<strong><?= addslashes(htmlspecialchars(ChurchMetaData::getChurchName())) ?></strong>')
                .addTo(map);

            // Track markers by classification for show/hide filtering
            var classMarkers = {};

            for (var i = 0; i < plotArray.length; i++) {
                var item = plotArray[i];
                if (!item.Latitude && !item.Longitude) { continue; }

                var clsId  = item.Classification;
                var color  = colorFor(clsId);
                var href   = bPlotFamily
                    ? 'v2/family/' + item.ID
                    : 'PersonView.php?PersonID=' + item.ID;

                var marker = L.circleMarker([item.Latitude, item.Longitude], {
                    radius:      8,
                    color:       color,
                    fillColor:   color,
                    fillOpacity: 0.85,
                    weight:      2
                });

                marker.bindPopup(
                    '<strong><a href="' + href + '">' + item.Salutation + '</a></strong>' +
                    '<br>' + item.Address
                );
                marker.addTo(map);

                if (!classMarkers[clsId]) { classMarkers[clsId] = []; }
                classMarkers[clsId].push(marker);
            }

            // Show/hide markers by classification
            window.CRM.map = {
                setClassificationVisible: function (clsId, visible) {
                    (classMarkers[clsId] || []).forEach(function (m) {
                        if (visible) { m.addTo(map); } else { map.removeLayer(m); }
                    });
                }
            };

            // Move desktop legend into the map (bottom-right corner)
            var legendControl = L.control({ position: 'bottomright' });
            legendControl.onAdd = function () {
                return document.getElementById('maplegend');
            };
            legendControl.addTo(map);

            // Legend checkbox interaction
            document.querySelectorAll('.legenditem-checkbox').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    var clsId = parseInt(cb.closest('.legenditem').dataset.classification, 10);
                    window.CRM.map.setClassificationVisible(clsId, cb.checked);
                });
            });
            document.querySelectorAll('.legenditem').forEach(function (item) {
                item.addEventListener('click', function (e) {
                    if (e.target.tagName !== 'INPUT') {
                        var cb = item.querySelector('input');
                        cb.checked = !cb.checked;
                        cb.dispatchEvent(new Event('change'));
                    }
                });
            });
        })();
    </script>

    <style nonce="<?= SystemURLs::getCSPNonce() ?>">
        .legend-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 4px;
            vertical-align: middle;
            border: 1px solid rgba(0, 0, 0, .25);
        }
    </style>
<?php } ?>

<?php require_once __DIR__ . '/Include/Footer.php'; ?>
