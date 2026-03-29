<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

function CompareDistance($elem1, $elem2)
{
    if ($elem1['Distance'] > $elem2['Distance']) {
        return 1;
    } elseif ($elem1['Distance'] === $elem2['Distance']) {
        return 0;
    } else {
        return -1;
    }
}

function SortByDistance($array)
{
    $newArr = $array;
    usort($newArr, 'CompareDistance');
    return $newArr;
}

// Create an associated array of family information sorted by distance from
// a particular family.
function FamilyInfoByDistance($iFamily)
{
    // Handle the degenerate case of no family selected by just making the array without
    // distance and bearing data, and don't bother to sort it.
    if ($iFamily) {
        // Get info for the selected family
        $selectedFamily = FamilyQuery::create()
            ->findOneById($iFamily);
    }

    // Compute distance and bearing from the selected family to all other families
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->find();

    foreach ($families as $family) {
        $familyID = $family->getId();
        if ($iFamily) {
            $results[$familyID]['Distance'] = floatval(GeoUtils::latLonDistance($selectedFamily->getLatitude(), $selectedFamily->getLongitude(), $family->getLatitude(), $family->getLongitude()));
            $results[$familyID]['Bearing'] = GeoUtils::latLonBearing($selectedFamily->getLatitude(), $selectedFamily->getLongitude(), $family->getLatitude(), $family->getLongitude());
        }
        $results[$familyID]['fam_Name'] = $family->getName();
        $results[$familyID]['fam_Address'] = $family->getAddress();
        $results[$familyID]['fam_Latitude'] = $family->getLatitude();
        $results[$familyID]['fam_Longitude'] = $family->getLongitude();
        $results[$familyID]['fam_ID'] = $familyID;
    }

    if ($iFamily) {
        $resultsByDistance = SortByDistance($results);
    } else {
        $resultsByDistance = $results;
    }
    return $resultsByDistance;
}

$sPageTitle = gettext('Family Geographic Utilities');
$sPageSubtitle = gettext('View and manage family location data');

// Create array with Classification Information (lst_ID = 1)
$classifications = Classification::getAll();

unset($aClassificationName);
$aClassificationName[0] = 'Unassigned';
foreach ($classifications as $classification) {
    $aClassificationName[intval($classification->getOptionId())] = $classification->getOptionName();
}

// Create array with Family Role Information (lst_ID = 2)
$familyRoles = ListOptionQuery::create()
    ->filterById(2)
    ->orderByOptionSequence()
    ->find();

unset($aFamilyRoleName);
$aFamilyRoleName[0] = 'Unassigned';
foreach ($familyRoles as $familyRole) {
    $aFamilyRoleName[intval($familyRole->getOptionId())] = $familyRole->getOptionName();
}

// Get the Family if specified in the query string
$iFamily = -1;
$iNumNeighbors = 15;
$nMaxDistance = 10;
if (array_key_exists('Family', $_GET)) {
    $iFamily = InputUtils::legacyFilterInput($_GET['Family'], 'int');
}
if (array_key_exists('NumNeighbors', $_GET)) {
    $iNumNeighbors = InputUtils::legacyFilterInput($_GET['NumNeighbors'], 'int');
}

$bClassificationPost = false;
$sClassificationList = '';
$sCoordFileFormat = '';
$sCoordFileFamilies = '';
$sCoordFileName = '';

//Is this the second pass?
if (isset($_POST['FindNeighbors']) || isset($_POST['DataFile']) || isset($_POST['PersonIDList'])) {
    //Get all the variables from the request object and assign them locally
    $iFamily = InputUtils::legacyFilterInput($_POST['Family']);
    $iNumNeighbors = InputUtils::legacyFilterInput($_POST['NumNeighbors']);
    $nMaxDistance = InputUtils::legacyFilterInput($_POST['MaxDistance']);
    $sCoordFileName = InputUtils::legacyFilterInput($_POST['CoordFileName']);
    if (array_key_exists('CoordFileFormat', $_POST)) {
        $sCoordFileFormat = InputUtils::legacyFilterInput($_POST['CoordFileFormat']);
    }
    if (array_key_exists('CoordFileFamilies', $_POST)) {
        $sCoordFileFamilies = InputUtils::legacyFilterInput($_POST['CoordFileFamilies']);
    }

    foreach ($aClassificationName as $key => $value) {
        $sClassNum = 'Classification' . $key;
        if (isset($_POST["$sClassNum"])) {
            $bClassificationPost = true;
            if (strlen($sClassificationList)) {
                $sClassificationList .= ',';
            }
            $sClassificationList .= $key;
        }
    }
}

if (isset($_POST['DataFile'])) {
    $resultsByDistance = FamilyInfoByDistance($iFamily);

    if ($sCoordFileFormat === 'GPSVisualizer') {
        $filename = $sCoordFileName . '.csv';
    } elseif ($sCoordFileFormat === 'StreetAtlasUSA') {
        $filename = $sCoordFileName . '.txt';
    }

    header("Content-Disposition: attachment; filename=$filename");

    if ($sCoordFileFormat === 'GPSVisualizer') {
        echo"Name,Latitude,Longitude\n";
    }

    $counter = 0;

    foreach ($resultsByDistance as $oneResult) {
        if ($sCoordFileFamilies === 'NeighborFamilies') {
            if ($counter++ === $iNumNeighbors) {
                break;
            }
            if ($oneResult['Distance'] > $nMaxDistance) {
                break;
            }
        }

        // Skip over the ones with no data
        if ($oneResult['fam_Latitude'] === 0) {
            continue;
        }

        if ($sCoordFileFormat === 'GPSVisualizer') {
            echo $oneResult['fam_Name'] . ',' . $oneResult['fam_Latitude'] . ',' . $oneResult['fam_Longitude'] ."\n";
        } elseif ($sCoordFileFormat === 'StreetAtlasUSA') {
            echo"BEGIN SYMBOL\n";
            echo $oneResult['fam_Latitude'] . ',' . $oneResult['fam_Longitude'] . ',' . $oneResult['fam_Name'] . ',' ."Green Star\n";
            echo"END\n";
        }
    }

    exit;
}

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('People'), '/people/dashboard'],
    [gettext('Geographic Utilities')],
]);
require_once __DIR__ . '/Include/Header.php';

//Get Families for the list
$families = FamilyQuery::create()
    ->filterByDateDeactivated(null)
    ->orderByName()
    ->find(); ?>
<form method="POST" action="GeoPage.php" name="GeoPage">
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <label for="Family" class="form-label"><?= gettext('Select Family') ?>:</label>
                <div>
                    <select name='Family' data-placeholder="<?= gettext('Select a family') ?>" class="form-select choiceSelectBox w-100">
                        <option></option>
                        <?php
                        foreach ($families as $family) {
                            echo"\n<option value=\"" . $family->getId() . '"';
                            if ($iFamily === $family->getId()) {
                                echo ' selected';
                            }
                            echo '>' . $family->getName() . '&nbsp;-&nbsp;' . $family->getAddress();
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="NumNeighbors" class="form-label"><?= gettext('Maximum number of neighbors') ?>:</label>
                <input type="text" class="form-control" name="NumNeighbors" value="<?= $iNumNeighbors ?>" style="max-width:120px">
            </div>
            <div class="mb-3">
                <label for="MaxDistance" class="form-label">
                    <?= gettext('Maximum distance') . ' (' . gettext(SystemConfig::getValue('sDistanceUnit')) . '):' ?>
                </label>
                <input type="text" class="form-control" name="MaxDistance" value="<?= $nMaxDistance ?>" style="max-width:120px">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= gettext('Show neighbors with these classifications') ?>:</label>
                <div class="row g-2">
                    <?php
                    foreach ($aClassificationName as $key => $value) {
                        $sClassNum = 'Classification' . $key;
                        $checked = (!$bClassificationPost || isset($_POST["$sClassNum"])); ?>
                        <div class="col-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" name="Classification<?= $key ?>"
                                    id="cls_<?= $key ?>" <?= ($checked ? 'checked' : '') ?>>
                                <label class="form-check-label" for="cls_<?= $key ?>"><?= _($value) ?></label>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
            <div class="mb-3">
                <input type="submit" class="btn btn-primary" name="FindNeighbors"
                    value="<?= gettext('Show Neighbors') ?>">
            </div>
        </div>

        <?php
        if (isset($_POST['FindNeighbors']) && !$iFamily) {
        ?>
            <div class="alert alert-warning">
                <?= gettext("Please select a Family.") ?>
            </div>
        <?php
        }
        ?>

        <!--Datafile section -->
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Make Data File') ?></h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><?= gettext('Data file format') ?>:</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="CoordFileFormat" id="fmt_gps"
                                value="GPSVisualizer" <?= ($sCoordFileFormat === 'GPSVisualizer' ? 'checked' : '') ?>>
                            <label class="form-check-label" for="fmt_gps"><?= gettext('GPS Visualizer') ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="CoordFileFormat" id="fmt_sa"
                                value="StreetAtlasUSA" <?= ($sCoordFileFormat === 'StreetAtlasUSA' ? 'checked' : '') ?>>
                            <label class="form-check-label" for="fmt_sa"><?= gettext('Street Atlas USA') ?></label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= gettext('Include families in the coordinate file') ?>:</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="CoordFileFamilies" id="fam_all"
                                value="AllFamilies" <?= ($sCoordFileFamilies === 'AllFamilies' ? 'checked' : '') ?>>
                            <label class="form-check-label" for="fam_all"><?= gettext('All Families') ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="CoordFileFamilies" id="fam_neighbor"
                                value="NeighborFamilies" <?= ($sCoordFileFamilies === 'NeighborFamilies' ? 'checked' : '') ?>>
                            <label class="form-check-label" for="fam_neighbor"><?= gettext('Neighbor Families') ?></label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="CoordFileName" class="form-label"><?= gettext('Coordinate database file name') ?>:</label>
                    <input type="text" class="form-control" name="CoordFileName" value="<?= InputUtils::escapeAttribute($sCoordFileName) ?>" style="max-width:300px">
                </div>
                <div class="mb-3">
                    <input type="submit" class="btn btn-primary" name="DataFile"
                        value="<?= gettext('Make Data File') ?>">
                </div>
            </div>
        </div>

    <div class="card mt-3">
        <?php
        $aPersonIDs = [];

        if (
            $iFamily !== 0 &&
            (isset($_POST['FindNeighbors']) ||
                isset($_POST['PersonIDList']))
        ) {
            $resultsByDistance = FamilyInfoByDistance($iFamily);

            $counter = 0; ?>
            <div class="card-body p-0">
            <table id="neighbours" class="table table-bordered data-table w-100">
                <thead>
                    <tr>
                        <th><?= gettext('Distance') ?></th>
                        <th><?= gettext('Direction') ?></th>
                        <th><?= gettext('Name') ?></th>
                        <th><?= gettext('Address') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($resultsByDistance as $oneResult) {
                        if ($counter >= $iNumNeighbors || $oneResult['Distance'] > $nMaxDistance) {
                            break;
                        } // Determine how many people in this family will be listed
                        $sSQL = 'SELECT * FROM person_per WHERE per_fam_ID=' . $oneResult['fam_ID'];
                        if ($bClassificationPost) {
                            $sSQL .= ' AND per_cls_ID IN (' . $sClassificationList . ')';
                        }
                        $rsPeople = RunQuery($sSQL);
                        $numListed = mysqli_num_rows($rsPeople);

                        if (!$numListed) { // skip families with zero members
                            continue;
                        }
                        $counter++; ?>
                        <tr class="table-active">
                            <td><?= $oneResult['Distance'] ?></td>
                            <td><?= InputUtils::escapeHTML($oneResult['Bearing']) ?>
                                <a target="_blank"
                                    href="https://www.google.com/maps/dir/Current+Location/<?= $oneResult['fam_Latitude'] . ',' . $oneResult['fam_Longitude'] ?>"><?= gettext('Direct me') ?></a>
                            </td>
                            <td><strong><?= InputUtils::escapeHTML($oneResult['fam_Name']) ?></strong></td>
                            <td>
                                <a target="_blank"
                                    href="http://maps.google.com/maps?q=<?= $oneResult['fam_Latitude'] . ',' . $oneResult['fam_Longitude'] ?>"><?= InputUtils::escapeHTML($oneResult['fam_Address']) ?></a>
                            </td>
                        </tr>
                        <?php
                        while ($aRow = mysqli_fetch_array($rsPeople)) {
                            extract($aRow);

                            if (!in_array($per_ID, $aPersonIDs)) {
                                $aPersonIDs[] = $per_ID;
                            } ?>
                            <tr>
                                <td></td>
                                <td></td>
                                <td class="text-end"><?= InputUtils::escapeHTML($per_FirstName . ' ' . $per_LastName) ?></td>
                                <td><?= InputUtils::escapeHTML($aClassificationName[$per_cls_ID]) ?></td>
                            </tr>
                    <?php
                        }
                    } ?>
                </tbody>
            </table>
            </div>

    <?php
            $sPersonIDList = implode(',', $aPersonIDs); ?>

    <input type="hidden" name="PersonIDList" value="<?= $sPersonIDList ?>">

    <div class="row">
        <div class="col-7 col-md-4">
            <a id="AddAllToCart" class="btn btn-primary"><?= gettext('Add All to Cart') ?></a>
        </div>
        <div class="col-7 col-md-4">
            <input name="IntersectCart" type="submit" class="btn btn-primary"
                value="<?= gettext('Intersect with Cart') ?>">
        </div>
        <div class="col-7 col-md-4">
            <a id="RemoveAllFromCart" class="btn btn-danger"><?= gettext('Remove All from Cart') ?></a>
        </div>
    </div>
<?php
        }
?>
    </div>
</form>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    var listPeople = <?= json_encode($aPersonIDs) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/GeoPage.js') ?>"></script>
<?php
require_once __DIR__ . '/Include/Footer.php';
