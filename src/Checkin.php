<?php

/*******************************************************************************
 *
 *  filename    : Checkin.php
 *  last change : 2007-xx-x
 *  description : Quickly add attendees to an event
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *  Copyright 2005 Todd Pillars
 *  Copyright 2012 Michael Wilt
  *
 ******************************************************************************/

$sPageTitle = gettext('Event Checkin');

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/Header.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttend;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$EventID = 0;
$CheckoutOrDelete = false;
$event = null;
$iChildID = 0 ;
$iAdultID = 0;


if (array_key_exists('EventID', $_POST)) {
    $EventID = InputUtils::legacyFilterInput($_POST['EventID'], 'int');
} // from ListEvents button=Attendees
if (isset($_POST['CheckOutBtn']) || isset($_POST['DeleteBtn'])) {
    $CheckoutOrDelete =  true;
}

if (isset($_POST['child-id'])) {
    $iChildID = InputUtils::legacyFilterInput($_POST['child-id'], 'int');
}
if (isset($_POST['adult-id'])) {
    $iAdultID = InputUtils::legacyFilterInput($_POST['adult-id'], 'int');
}

//
// process the action inputs
//

//Start off by first picking the event to check people in for
$activeEvents = EventQuery::Create()
    ->filterByInActive(1, Criteria::NOT_EQUAL)
    ->find();

if ($EventID > 0) {
    //get Event Details
    $event = EventQuery::Create()
        ->findOneById($EventID);
}
?>
<div id="errorcallout" class="callout callout-danger" hidden></div>

<!--Select Event Form -->
<form class="well form-horizontal" name="selectEvent" action="Checkin.php" method="POST">
    <div class="row">
        <div class="col-md-10 col-xs-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Select the event to which you would like to check people in for') ?>
                        :</h3>
                </div>
                <div class="card-body">
                    <?php if ($sGlobalMessage) : ?>
                        <p><?= $sGlobalMessage ?></p>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="col-md-2 control-label"><?= gettext('Select Event'); ?></label>
                        <div class="col-md-10 inputGroupContainer">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-calendar-check fa-2xl"> </i> </span> &nbsp;
                                <select id="EventID" name="EventID" class="form-control" onchange="this.form.submit()">
                                    <option value="<?= $EventID; ?>"
                                            disabled <?= ($EventID == 0) ? " Selected='selected'" : "" ?> ><?= gettext('Select event') ?></option>
                                    <?php foreach ($activeEvents as $event) {
                                        ?>
                                        <option
                                            value="<?= $event->getId(); ?>" <?= ($EventID == $event->getId()) ? " Selected='selected'" : "" ?> >
                                            <?= $event->getTitle(); ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-12 text-right">
                            <a class="btn btn-primary" href="EventEditor.php"><?= gettext('Add New Event'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form> <!-- end selectEvent form -->

<!-- Add Attendees Form -->
<?php
// If event is known, then show 2 text boxes, person being checked in and the person checking them in.
// Show a verify button and a button to add new visitor in dbase.
if (!$CheckoutOrDelete &&  $EventID > 0) {
    ?>

    <form class="well form-horizontal" method="post" action="Checkin.php" id="AddAttendees" data-toggle="validator"
          role="form">
        <input type="hidden" id="EventID" name="EventID" value="<?= $EventID; ?>">
        <input type="hidden" id="child-id" name="child-id">
        <input type="hidden" id="adult-id" name="adult-id">

        <div class="row">
            <div class="col-md-10 col-xs-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><?= gettext('Add Attendees for Event'); ?>: <?= $event->getTitle() ?></h3>
                    </div>
                    <div class="card-body">

                        <div class="form-group">
                            <label for="child" class="col-sm-2 control-label"><?= gettext("Person's Name") ?></label>
                            <div class="col-sm-5 inputGroupContainer">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-child fa-2xl"></i></span> &nbsp;
                                    <input type="text" class="form-control" id="child"
                                           placeholder="<?= gettext("Person's Name"); ?>" required tabindex=1>
                                </div>
                                <span class="glyphicon form-control-feedback" aria-hidden="true"></span>
                                <div class="help-block with-errors"></div>
                            </div>
                            <div id="childDetails" class="col-sm-5 text-center"></div>
                        </div>
                        <hr>
                        <div class="form-group">
                            <label for="adult"
                                   class="col-sm-2 control-label"><?= gettext('Adult Name (Optional)') ?></label>
                            <div class="col-sm-5 inputGroupContainer">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-user fa-2xl"></i></span> &nbsp;
                                    <input type="text" class="form-control" id="adult"
                                           placeholder="<?= gettext('Checked in By (Optional)'); ?>" tabindex=2>
                                </div>
                            </div>
                            <div id="adultDetails" class="col-sm-5 text-center"></div>
                        </div>

                        <div class="form-group row">

                            <div class="card-footer text-center col-md-4  col-xs-8">
                                <input type="submit" class="btn btn-primary" value="<?= gettext('CheckIn'); ?>"
                                       name="CheckIn" tabindex=3>
                                <input type="reset" class="btn btn-default" value="<?= gettext('Cancel'); ?>"
                                       name="Cancel" tabindex=4 onClick="SetPersonHtml($('#childDetails'),null);SetPersonHtml($('#adultDetails'),null);">
                            </div>

                            <div class="text-right col-md-8 col-xs-4">
                                <a class="btn btn-success" href="PersonEditor.php"><?= gettext('Add Visitor'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form> <!-- end AddAttendees form -->

    <?php
}

// Checkin/Checkout Section update db
if (isset($_POST['EventID']) && isset($_POST['child-id']) && (isset($_POST['CheckIn']) || isset($_POST['CheckOut']) || isset($_POST['Delete']))) {
    //Fields -> event_id, person_id, checkin_date, checkin_id, checkout_date, checkout_id
    if (isset($_POST['CheckIn']) && !empty($iChildID)) {
        $attendee = EventAttendQuery::create()->filterByEventId($EventID)->findOneByPersonId($iChildID);
        if ($attendee) {
            ?>
            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                $('#errorcallout').text('<?= gettext("Person has been already checked in for this event") ?>').fadeIn();
            </script>
            <?php
        } else {
            $attendee = new EventAttend();
            $attendee->setEventId($EventID);
            $attendee->setPersonId($iChildID);
            $attendee->setCheckinDate(date("Y-m-d H:i:s"));
            if (!empty($iAdultID)) {
                $attendee->setCheckinId($iAdultID);
            }
            $attendee->save();
        }
    }

    //Checkout Update
    if (isset($_POST['CheckOut'])) {
        $values = "checkout_date=NOW(), checkout_id=" . ($iAdultID ? "'" . $iAdultID . "'" : 'null');
        $attendee = EventAttendQuery::create()
            ->filterByEventId($EventID)
            ->findOneByPersonId($iChildID);
        $attendee->setCheckoutDate(date("Y-m-d H:i:s"));
        if ($iAdultID) {
            $attendee->setCheckoutId($iAdultID);
        }
        $attendee->save();
    }


    //delete
    if (isset($_POST['Delete'])) {
        EventAttendQuery::create()
            ->filterByEventId($EventID)
            ->findOneByPersonId($iChildID)
            ->delete();
    }
}

//-- End checkin

//  Checkout / Delete section
if (
    isset($_POST['EventID']) && isset($_POST['child-id']) &&
    (isset($_POST['CheckOutBtn']) || isset($_POST['DeleteBtn']))
) {
    $iChildID = InputUtils::legacyFilterInput($_POST['child-id'], 'int');

    $formTitle = (isset($_POST['CheckOutBtn']) ? gettext("CheckOut Person") : gettext("Delete Checkin in Entry")); ?>

    <form class="well form-horizontal" method="post" action="Checkin.php" id="CheckOut" data-toggle="validator"
          role="form">
        <input type="hidden" name="EventID" value="<?= $EventID ?>">
        <input type="hidden" name="child-id" value="<?= $iChildID ?>">

        <div class="row">
            <div class="col-xs-12">
                <div class="card card-primary">
                    <div class="card-header with-border">
                        <h3 class="card-title"><?= $formTitle ?></h3>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div id="child" class="col-sm-4 text-center" onload="SetPersonHtml(this,perArr)">
                                <?php
                                loadperson($iChildID); ?>
                            </div>
                            <?php
                            if (isset($_POST['CheckOutBtn'])) {
                                ?>
                                <div class="col-sm-4 col-xs-6">
                                    <div class="form-group">
                                        <label><?= gettext('Adult Checking Out Person') ?>:</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="fa fa-user"></i></span>
                                            <input type="text" id="adultout" name="adult" class="form-control"
                                               placeholder="<?= gettext('Adult Name (Optional)') ?>">
                                            </div>
                                        <input type="hidden" id="adultout-id" name="adult-id">
                                    </div>
                                    <div class="form-group">
                                        <input type="submit" class="btn btn-primary"
                                               value="<?= gettext('CheckOut') ?>" name="CheckOut">
                                        <input type="submit" class="btn btn-default" value="<?= gettext('Cancel') ?>"
                                               name="CheckoutCancel">
                                    </div>
                                </div>

                                <div class="col-sm-4 text-center">
                                    <div id="adultoutDetails" class="card card-solid box-default hidden"></div>
                                </div>
                                <?php
                            } else { // DeleteBtn?>
                                <div class="form-group">
                                    <input type="submit" class="btn btn-danger"
                                           value="<?= gettext('Delete') ?>" name="Delete">
                                    <input type="submit" class="btn btn-default" value="<?= gettext('Cancel') ?>"
                                           name="DeleteCancel">
                                </div>
                                <?php
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php
}
//End checkout
//**********************************************************************************************************

//Populate data table
if (isset($_POST['EventID'])) {
    ?>
    <div class="card card-primary">
        <div class="card-body table-responsive">
            <table id="checkedinTable" class="table data-table table-striped ">
                <thead>
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('Checked In Time') ?></th>
                    <th><?= gettext('Checked In By') ?></th>
                    <th><?= gettext('Checked Out Time') ?></th>
                    <th><?= gettext('Checked Out By') ?></th>
                    <th nowrap><?= gettext('Action') ?></th>
                </tr>
                </thead>
                <tbody>

                <?php
                //Get Event Attendees details
                $eventAttendees = EventAttendQuery::create()
                    ->filterByEventId($EventID)
                    ->find();

                foreach ($eventAttendees as $per) {
                    //Get Person who is checked in
                    $checkedInPerson = PersonQuery::create()
                        ->findOneById($per->getPersonId());

                    $sPerson = $checkedInPerson->getFullName();

                    //Get Person who checked person in
                    $sCheckinby = "";
                    if ($per->getCheckinId()) {
                        $checkedInBy = PersonQuery::create()
                            ->findOneById($per->getCheckinId());
                        $sCheckinby = $checkedInBy->getFullName();
                    }

                    //Get Person who checked person out
                    $sCheckoutby = "";
                    if ($per->getCheckoutId()) {
                        $checkedOutBy = PersonQuery::create()
                            ->findOneById($per->getCheckoutId());
                        $sCheckoutby = $checkedOutBy->getFullName();
                    } ?>
                    <tr>
                        <td><img src="<?= SystemURLs::getRootPath() . '/api/person/' . $per->getPersonId() . '/thumbnail' ?>"
                                 class="direct-chat-img initials-image">&nbsp
                            <a href="PersonView.php?PersonID=<?= $per->getPersonId() ?>"><?= $sPerson ?></a></td>
                        <td><?= date_format($per->getCheckinDate(), SystemConfig::getValue('sDateTimeFormat')) ?></td>
                        <td><?= $sCheckinby ?></td>
                        <td><?= $per->getCheckoutDate() ? date_format($per->getCheckoutDate(), SystemConfig::getValue('sDateTimeFormat'))  : '' ?></td>
                        <td><?= $sCheckoutby ?></td>

                        <td align="center">
                            <form method="POST" action="Checkin.php" name="DeletePersonFromEvent">
                                <input type="hidden" name="child-id" value="<?= $per->getPersonId() ?>">
                                <input type="hidden" name="EventID" value="<?= $EventID ?>">
                                <?php
                                if (!$per->getCheckoutDate()) {
                                    ?>
                                    <input class="btn btn-primary btn-sm" type="submit" name="CheckOutBtn"
                                           value="<?= gettext('CheckOut') ?>">
                                    <input class="btn btn-danger btn-sm" type="submit" name="DeleteBtn"
                                           value="<?= gettext('Delete') ?>">

                                    <?php
                                } else {
                                    ?>
                                    <i class="fa fa-check-circle"></i>
                                    <?php
                                } ?>
                            </form>
                        </td>
                    </tr>
                                <?php
                } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
    var perArr;
    $(document).ready(function () {
        $('#checkedinTable').DataTable(window.CRM.plugin.dataTable);
    });

    $(document).ready(function() {
        var $input = $("#child, #adult, #adultout");
        $input.autocomplete({
            source: function (request, response) {
                $.ajax({
                    url: window.CRM.root + '/api/persons/search/'+request.term,
                    dataType: 'json',
                    type: 'GET',
                    success: function (data) {
                        response($.map(data, function (item) {
                            return {
                                label: item.text,
                                value: item.objid,
                                obj:item
                            };
                        }));
                    }
                })
            },
            minLength: 2,
            select: function(event,ui) {
                $('[id=' + event.target.id + ']' ).val(ui.item.obj.text);
                $('[id=' + event.target.id + '-id]').val(ui.item.obj.objid);
                SetPersonHtml($('#' + event.target.id + 'Details'),ui.item.obj);
                return false;
            }
        });

    });

    function SetPersonHtml(element, perArr) {
        if(perArr) {
            element.html(
                '<div class="text-center">' +
                '<a target="_top" href="PersonView.php?PersonID=' + perArr.objid + '"><h4>' + perArr.text + '</h4></a>' +
                '<img src="' + window.CRM.root + '/api/person/' + perArr.objid + '/thumbnail"' +
                'class="initials-image profile-user-img img-responsive img-circle"> </div>'
            );
            element.removeClass('hidden');
        } else {
            element.html('');
            element.addClass('hidden');
        }
    }
</script>
<?php require 'Include/Footer.php';

function loadPerson($iPersonID)
{
    if ($iPersonID == 0) {
        echo "";
    }
    $person = PersonQuery::create()
        ->findOneById($iPersonID);
    $familyRole = "(";
    if ($person->getFamId()) {
        if ($person->getFamilyRole()) {
            $familyRole .= $person->getFamilyRoleName();
        } else {
            $familyRole .=  gettext('Member');
        }
        $familyRole .= gettext(' of the') . ' <a href="v2/family/' . $person->getFamId() . '">' . $person->getFamily()->getName() . '</a> ' . gettext('family') . ' )';
    } else {
        $familyRole = gettext('(No assigned family)');
    }


    $html = '<div class="text-center">' .
        '<a target="_top" href="PersonView.php?PersonID=' . $iPersonID . '"><h4>' . $person->getTitle() . ' ' . $person->getFullName() . '</h4></a>' .
        '<div class="">' . $familyRole . '</div>' .
        '<div class="text-center">' . $person->getAddress() . '</div>' .
        '<img src="' . SystemURLs::getRootPath() . '/api/person/' . $iPersonID . '/thumbnail" class="initials-image profile-user-img img-responsive img-circle"> </div>';
    echo $html;
}
?>
