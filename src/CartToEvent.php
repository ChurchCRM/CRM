<?php

/*******************************************************************************
 *
 *  filename    : CartToEvent.php
 *  last change : 2005-09-09
 *  description : Add cart records to an event
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *  Copyright 2005 Todd Pillars
 *  Copyright 2012 Michael Wilt
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\model\ChurchCRM\EventAttend;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups & Roles permission
if (!AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

// Was the form submitted?
if (isset($_POST['Submit']) && count($_SESSION['aPeopleCart']) > 0 && isset($_POST['EventID'])) {
    $iEventID = InputUtils::legacyFilterInput($_POST['EventID'], 'int');

    $iCount = 0;
    foreach ($_SESSION['aPeopleCart'] as $element) {
        try {
            $eventAttend = new EventAttend();
            $eventAttend
                ->setEventId($iEventID)
                ->setPersonId($element);
            $eventAttend->save();
            $iCount++;
        } catch (\Throwable $ex) {
            $logger = LoggerUtils::getAppLogger();
            $logger->error('An error occurred when saving event attendance', ['exception' => $ex]);
        }
    }
    Cart::emptyAll();

    $sGlobalMessage = $iCount . ' records(s) successfully added to selected Event.';
    // TODO: do this in API
    RedirectUtils::redirect('v2/cart?Action=EmptyCart&Message=aMessage&iCount=' . $iCount . '&iEID=' . $iEventID);
}

// Set the page title and include HTML header
$sPageTitle = gettext('Add Cart to Event');
require 'Include/Header.php';

if (count($_SESSION['aPeopleCart']) > 0) {
    $sSQL = 'SELECT event_id, event_title FROM events_event';
    $rsEvents = RunQuery($sSQL); ?>
<div class="card">
<p align="center"><?= gettext('Select the event to which you would like to add your cart') ?>:</p>
<form name="CartToEvent" action="CartToEvent.php" method="POST">
<table align="center">
        <?php if ($sGlobalMessage) {
            ?>
        <tr>
          <td colspan="2"><?= $sGlobalMessage ?></td>
        </tr>
            <?php
        } ?>
        <tr>
                <td class="LabelColumn"><?= gettext('Select Event') ?>:</td>
                <td class="TextColumn">
                        <?php
                        // Create the group select drop-down
                        echo '<select name="EventID">';
                        while ($aRow = mysqli_fetch_array($rsEvents)) {
                            echo '<option value="' . $aRow['event_id'] . '">' . $aRow['event_title'] . '</option>';
                        }
                        echo '</select>'; ?>
                </td>
        </tr>
</table>
<p align="center">
<BR>
<input type="submit" name="Submit" value=<?= '"' . gettext('Add Cart to Event') . '"' ?> class="btn btn-primary">
<BR><BR>--<?= gettext('OR') ?>--<BR><BR>
<a href="EventEditor.php" class="btn btn-info"><?= gettext('Add New Event') ?></a>
<BR><BR>
</p>
</form>
</div>
    <?php
} else {
        echo '<p align="center" class="callout callout-warning">' . gettext('Your cart is empty!') . '</p>';
}

require 'Include/Footer.php';
?>
