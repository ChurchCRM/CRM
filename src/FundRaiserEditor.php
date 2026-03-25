<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FundRaiser;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Create New Fund Raiser');

// Check if linkBack was explicitly provided (not the fallback)
$linkBackProvided = isset($_GET['linkBack']) && $_GET['linkBack'] !== '';
$linkBack = RedirectUtils::getLinkBackFromRequest('FindFundRaiser.php');
$iFundRaiserID = InputUtils::legacyFilterInputArr($_GET, 'FundRaiserID');

$fundraiser = null;
if ($iFundRaiserID > 0) {
    // Get the current fundraiser record
    $fundraiser = FundRaiserQuery::create()->findOneById($iFundRaiserID);
    $sPageTitle = gettext('Fundraiser') . ' #' . $iFundRaiserID . ' ' . $fundraiser->getTitle();
    // Set current fundraiser
    $_SESSION['iCurrentFundraiser'] = $iFundRaiserID;
}

$sDateError = '';

// Is this the second pass?
if (isset($_POST['FundRaiserSubmit'])) {
    //Get all the variables from the request object and assign them locally
    $dDate = InputUtils::legacyFilterInputArr($_POST, 'Date');
    $sTitle = InputUtils::legacyFilterInputArr($_POST, 'Title');
    $sDescription = InputUtils::legacyFilterInputArr($_POST, 'Description');

    // Initialize the error flag
    $bErrorFlag = false;

    // Validate Date
    if (strlen($dDate) > 0) {
        list($iYear, $iMonth, $iDay) = sscanf($dDate, '%04d-%02d-%02d');
        if (!checkdate($iMonth, $iDay, $iYear)) {
            $sDateError = '<span class="text-error">' . gettext('Not a valid date') . '</span>';
            $bErrorFlag = true;
        }
    }

    // If no errors, then let's update...
    if (!$bErrorFlag) {
        // New deposit slip
        if ($iFundRaiserID <= 0) {
            $fundraiser = new FundRaiser();
            $fundraiser
                ->setDate($dDate)
                ->setTitle($sTitle)
                ->setDescription($sDescription)
                ->setEnteredBy(AuthenticationManager::getCurrentUser()->getId())
                ->setEnteredDate(DateTimeUtils::getToday()->format('YmdHis'));
            $fundraiser->save();
            $fundraiser->reload();

            $iFundRaiserID = $fundraiser->getId();
            // Existing record (update)
        } else {
            $fundraiser = FundRaiserQuery::create()->findOneById($iFundRaiserID);
            $fundraiser
                ->setDate($dDate)
                ->setTitle($sTitle)
                ->setDescription($sDescription)
                ->setEnteredBy(AuthenticationManager::getCurrentUser()->getId())
                ->setEnteredDate(DateTimeUtils::getToday()->format('YmdHis'));
            $fundraiser->save();
        }

        $_SESSION['iCurrentFundraiser'] = $iFundRaiserID;

        if (isset($_POST['FundRaiserSubmit'])) {
            // Only use linkBack if it was explicitly provided in the original request
            if ($linkBackProvided) {
                RedirectUtils::redirect($linkBack);
            } else {
                //Send to the view of this FundRaiser
                RedirectUtils::redirect('FundRaiserEditor.php?FundRaiserID=' . $iFundRaiserID);
            }
        }
    }
} else {
    //FirstPass
    //Are we editing or adding?
    if ($fundraiser) {
        //Editing....
        //Get all the data on this record
        $dDate = $fundraiser->getDate();
        $sTitle = $fundraiser->getTitle();
        $sDescription = $fundraiser->getDescription();


        $sSQL ="SELECT di_ID, di_Item, di_multibuy,
        a.per_FirstName as donorFirstName, a.per_LastName as donorLastName,
        b.per_FirstName as buyerFirstName, b.per_LastName as buyerLastName,
        di_title, di_sellprice, di_estprice, di_materialvalue, di_minimum
        FROM donateditem_di
        LEFT JOIN person_per a ON di_donor_ID=a.per_ID
        LEFT JOIN person_per b ON di_buyer_ID=b.per_ID
        WHERE di_FR_ID = '" . $iFundRaiserID ."' ORDER BY di_multibuy,SUBSTR(di_item,1,1),cast(SUBSTR(di_item,2) as unsigned integer),SUBSTR(di_item,4)";
        $rsDonatedItems = RunQuery($sSQL);
        $_SESSION['iCurrentFundraiser'] = $iFundRaiserID;        // Probably redundant

    } else {
        $dDate = date_create('now');    // Set default date to today
        $sTitle = '';
        $sDescription = '';
        $rsDonatedItems = 0;
    }
}

require_once __DIR__ . '/Include/Header.php';

?>
<div class="card-body">
    <form method="post" action="FundRaiserEditor.php?<?= ($linkBackProvided ? 'linkBack=' . urlencode($linkBack) . '&' : '') . 'FundRaiserID=' . $iFundRaiserID ?>" name="FundRaiserEditor">

        <table cellpadding="3" width="100%">
            <tr>
                <td>
                    <table cellpadding="3">
                        <tr>
                            <td class="LabelColumn"><?= gettext('Date') ?>:</td>
                            <td class="TextColumn"><input type="text" name="Date" value="<?= $dDate->format("Y-m-d") ?>" maxlength="10" id="Date" size="11" class="date-picker"><span class="text-error"><?= $sDateError ?></span></td>
                        </tr>

                        <tr>
                            <td class="LabelColumn"><?= gettext('Title') ?>:</td>
                            <td class="TextColumn"><input type="text" size="50" name="Title" id="Title" value="<?= $sTitle ?>"></td>
                        </tr>

                        <tr>
                            <td class="LabelColumn"><?= gettext('Description') ?>:</td>
                            <td class="TextColumn"><textarea name="Description" id="Description" cols="50" rows="5"><?= $sDescription ?></textarea></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td align="right">
                    <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FundRaiserSubmit">
                    <input type="button" class="btn btn-danger" value="<?= gettext('Cancel') ?>" name="FundRaiserCancel" onclick="javascript:document.location='FindFundRaiser.php';">
                </td>
            </tr>
         
    </form>
    </table>

</div>
<?php if ($iFundRaiserID > 0): ?>
<div class="card-body">
    <div class="d-flex flex-wrap gap-2">
        <a href="DonatedItemEditor.php?CurrentFundraiser=<?= $iFundRaiserID ?>&linkBack=FundRaiserEditor.php?FundRaiserID=<?= $iFundRaiserID ?>&CurrentFundraiser=<?= $iFundRaiserID ?>" class="btn btn-success">
            <i class="ti ti-plus me-1"></i><?= gettext('Add Donated Item') ?>
        </a>
        <div class="dropdown">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ti ti-file-text me-1"></i><?= gettext('Reports') ?>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="Reports/FRCatalog.php?CurrentFundraiser=<?= $iFundRaiserID ?>">
                    <i class="ti ti-book me-2"></i><?= gettext('Generate Catalog') ?>
                </a>
                <a class="dropdown-item" href="Reports/FRBidSheets.php?CurrentFundraiser=<?= $iFundRaiserID ?>">
                    <i class="ti ti-list me-2"></i><?= gettext('Generate Bid Sheets') ?>
                </a>
                <a class="dropdown-item" href="Reports/FRCertificates.php?CurrentFundraiser=<?= $iFundRaiserID ?>">
                    <i class="ti ti-certificate me-2"></i><?= gettext('Generate Certificates') ?>
                </a>
            </div>
        </div>
        <a href="BatchWinnerEntry.php?CurrentFundraiser=<?= $iFundRaiserID ?>&linkBack=FundRaiserEditor.php?FundRaiserID=<?= $iFundRaiserID ?>&CurrentFundraiser=<?= $iFundRaiserID ?>" class="btn btn-secondary">
            <i class="ti ti-trophy me-1"></i><?= gettext('Batch Winner Entry') ?>
        </a>
    </div>
</div>
<div class="card-body" style="overflow: visible;">
    <h6 class="fw-bold mb-3"><?= gettext('Donated items for this fundraiser') ?></h6>
    <table class="table table-vcenter table-hover w-100">
        <thead>
        <tr>
            <th><?= gettext('Item') ?></th>
            <th><?= gettext('Multiple') ?></th>
            <th><?= gettext('Donor') ?></th>
            <th><?= gettext('Buyer') ?></th>
            <th><?= gettext('Title') ?></th>
            <th><?= gettext('Sale Price') ?></th>
            <th><?= gettext('Est. Value') ?></th>
            <th><?= gettext('Material') ?></th>
            <th><?= gettext('Minimum') ?></th>
            <th class="w-1 no-export"><?= gettext('Actions') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        //Loop through all donated items
        if ($rsDonatedItems != 0) {
            while ($aRow = mysqli_fetch_array($rsDonatedItems)) {
                extract($aRow);

                if ($di_Item == '') {
                    $di_Item = '~';
                }

                ?>
                <tr>
                    <td><?= InputUtils::escapeHTML($di_Item) ?></td>
                    <td><?= $di_multibuy ? '<span class="badge bg-info">X</span>' : '' ?></td>
                    <td><?= InputUtils::escapeHTML($donorFirstName) . ' ' . InputUtils::escapeHTML($donorLastName) ?></td>
                    <td>
                        <?php if ($di_multibuy) {
                            echo '<span class="text-muted">' . gettext('Multiple') . '</span>';
                        } else {
                            echo InputUtils::escapeHTML($buyerFirstName) . ' ' . InputUtils::escapeHTML($buyerLastName);
                        } ?>
                    </td>
                    <td><?= InputUtils::escapeHTML($di_title) ?></td>
                    <td class="text-end"><?= InputUtils::escapeHTML($di_sellprice) ?></td>
                    <td class="text-end"><?= InputUtils::escapeHTML($di_estprice) ?></td>
                    <td class="text-end"><?= InputUtils::escapeHTML($di_materialvalue) ?></td>
                    <td class="text-end"><?= InputUtils::escapeHTML($di_minimum) ?></td>
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="DonatedItemEditor.php?DonatedItemID=<?= (int)$di_ID ?>&linkBack=FundRaiserEditor.php?FundRaiserID=<?= (int)$iFundRaiserID ?>">
                                    <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="DonatedItemDelete.php?DonatedItemID=<?= (int)$di_ID ?>&linkBack=FundRaiserEditor.php?FundRaiserID=<?= (int)$iFundRaiserID ?>">
                                    <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php
            } // while
        } // if
        ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php
require_once __DIR__ . '/Include/Footer.php';
