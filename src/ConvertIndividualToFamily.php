<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

// Security
AuthenticationManager::redirectHomeIfNotAdmin();

$bConvert = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($bConvert && !CSRFUtils::verifyRequest($_POST)) {
    http_response_code(403);
    die(gettext('Invalid CSRF token'));
}
$bDoAll = $bConvert && ($_POST['all'] ?? '') === 'true';

$sPageTitle = gettext('Convert Individuals to Families');
$sPageSubtitle = gettext('Convert individual records into family units');

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('People'), '/people/dashboard'],
    [gettext('Convert to Family')],
]);
require_once __DIR__ . '/Include/Header.php';

echo '<div class="card-body"><pre class="pre-compact">';

$curUserId = AuthenticationManager::getCurrentUser()->getId();

// Get list of people that are not assigned to a family
$unassignedPersons = PersonQuery::create()
    ->filterByFamId(0)
    ->orderByLastName()
    ->orderByFirstName()
    ->find();

foreach ($bConvert ? $unassignedPersons : [] as $person) {
    echo '<br><br><br>';
    echo '*****************************************';

    $family = new Family();
    $family
        ->setName($person->getLastName())
        ->setAddress1($person->getAddress1())
        ->setAddress2($person->getAddress2())
        ->setCity($person->getCity())
        ->setState($person->getState())
        ->setZip($person->getZip())
        ->setCountry($person->getCountry())
        ->setHomePhone($person->getHomePhone())
        ->setDateEntered(new \DateTimeImmutable())
        ->setEnteredBy($curUserId);
    $family->save();

    $iFamilyID = $family->getId();

    echo '<br><br>';

    // Now update person record — move to new family and clear address fields
    $person
        ->setFamId($iFamilyID)
        ->setAddress1(null)
        ->setAddress2(null)
        ->setCity(null)
        ->setState(null)
        ->setZip(null)
        ->setCountry(null)
        ->setHomePhone(null)
        ->setDateLastEdited(new \DateTimeImmutable())
        ->setEditedBy($curUserId);
    $person->save();

    echo '<br><br><br>';
    echo InputUtils::escapeHTML($person->getFirstName()) . ' ' . InputUtils::escapeHTML($person->getLastName()) . ' (per_ID = ' . (int) $person->getId() . ') is now part of the ';
    echo InputUtils::escapeHTML($person->getLastName()) . ' Family (fam_ID = ' . (int) $iFamilyID . ')<br>';
    echo '*****************************************';

    if (!$bDoAll) {
        break;
    }
}
echo '</pre>';
if (!$bConvert) {
    echo '<p>' . sprintf(gettext('People without a family: %d'), $unassignedPersons->count()) . '</p>';
}
echo '<form method="post" action="ConvertIndividualToFamily.php" class="mt-3">';
echo CSRFUtils::getTokenInputField();
echo '<button type="submit" class="btn btn-primary me-2">' . gettext('Convert Next') . '</button>';
echo '<button type="submit" name="all" value="true" class="btn btn-warning">' . gettext('Convert All') . '</button>';
echo '</form>';
echo '</div>';

require_once __DIR__ . '/Include/Footer.php';
