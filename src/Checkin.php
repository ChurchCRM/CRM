<?php
/*******************************************************************************
 *
 *  filename    : Checkin.php
 *  last change : 2007-xx-x
 *  description : Quickly add attendees to an event
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *  Copyright 2005 Todd Pillars
 *  Copyright 2012 Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

$sPageTitle = gettext('Event Checkin');

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/Header.php';

use ChurchCRM\EventQuery;
use ChurchCRM\EventAttendQuery;
use ChurchCRM\EventAttend;
use ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;

$EventID = 0;
$CheckoutOrDelete = false;
$event = null;
$iChildID = 0 ;
$iAdultID = 0;


if (array_key_exists('EventID', $_POST)) {
    $EventID = InputUtils::LegacyFilterInput($_POST['EventID'], 'int');
} // from ListEvents button=Attendees
if (isset($_POST['CheckOutBtn']) || isset($_POST['DeleteBtn'])) {
    $CheckoutOrDelete =  true;
}

if (isset($_POST['child-id'])) {
    $iChildID = InputUtils::LegacyFilterInput($_POST['child-id'], 'int');
}
if (isset($_POST['adult-id'])) {
    $iAdultID = InputUtils::LegacyFilterInput($_POST['adult-id'], 'int');
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
            <div class="box box-primary">
                <div class="box-header">
                    <h3 class="box-title"><?= gettext('Select the event to which you would like to check people in for') ?>
                        :</h3>
                </div>
                <div class="box-body">
                    <?php if ($sGlobalMessage): ?>
                        <p><?= $sGlobalMessage ?></p>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="col-md-2 control-label"><?= gettext('Select Event'); ?></label>
                        <div class="col-md-10 inputGroupContainer">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-calendar-check-o"></i></span>
                                <select name="EventID" class="form-control" onchange="this.form.submit()">
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
                            <a href="EventEditor.php"><?= gettext('Add New Event'); ?></a>
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
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title"><?= gettext('Add Attendees for Event'); ?>: <?= $event->getTitle() ?></h3>
                    </div>
                    <div class="box-body">

                        <div class="form-group">
                            <label for="child" class="col-sm-2 control-label"><?= gettext("Person's Name") ?></label>
                            <div class="col-sm-5 inputGroupContainer">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-child"></i></span>
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
                                   class="col-sm-2 control-label"><?= gettext('Adult Name(Optional)') ?></label>
                            <div class="col-sm-5 inputGroupContainer">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-user"></i></span>
                                    <input type="text" class="form-control" id="adult"
                                           placeholder="<?= gettext('Checked in By(Optional)'); ?>" tabindex=2>
                                </div>
                            </div>
                            <div id="adultDetails" class="col-sm-5 text-center"></div>
                        </div>

                        <div class="form-group row">

                            <div class="box-footer text-center col-md-4  col-xs-8">
                                <input type="submit" class="btn btn-primary" value="<?= gettext('CheckIn'); ?>"
                                       name="CheckIn" tabindex=3>
                                <input type="reset" class="btn btn-default" value="<?= gettext('Cancel'); ?>"
                                       name="Cancel" tabindex=4 onClick="SetPersonHtml($('#childDetails'),null);SetPersonHtml($('#adultDetails'),null);">
                            </div>

                            <div class="text-right col-md-8 col-xs-4">
                                <a href="PersonEditor.php"><?= gettext('Add Visitor'); ?></a>
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
    if (isset($_POST['CheckIn']) && $iChildID > 0) {
        $attendee = EventAttendQuery::create()
            ->filterByEventId($EventID)
            ->findOneByPersonId($iChildID);
        if ($attendee) {
            ?>
            <script>
                $('#errorcallout').text('<?= gettext("Person has been already checked in for this event") ?>').fadeIn();
            </script>
            <?php
        } else {
            $attendee = new EventAttend();
            $attendee->setEventId($EventID);
            $attendee->setPersonId($iChildID);
            $attendee->setCheckinDate(date("Y-m-d H:i:s"));
            if ($iAdultID) {
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
if (isset($_POST['EventID']) && isset($_POST['child-id']) &&
    (isset($_POST['CheckOutBtn']) || isset($_POST['DeleteBtn']))
) {
    $iChildID = InputUtils::LegacyFilterInput($_POST['child-id'], 'int');

    $formTitle = (isset($_POST['CheckOutBtn']) ? gettext("CheckOut Person") : gettext("Delete Checkin in Entry")); ?>

    <form class="well form-horizontal" method="post" action="Checkin.php" id="CheckOut" data-toggle="validator"
          role="form">
        <input type="hidden" name="EventID" value="<?= $EventID ?>">
        <input type="hidden" name="child-id" value="<?= $iChildID ?>">

        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><?= $formTitle ?></h3>
                    </div>

                    <div class="box-body">
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
                                    <div id="adultoutDetails" class="box box-solid box-default hidden"></div>
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
    <div class="box box-primary">
        <div class="box-body table-responsive">
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
                        <td><img data-name="<?= $sPerson; ?>"
                                 data-src="<?= SystemURLs::getRootPath() . '/api/persons/' . $per->getPersonId() . '/thumbnail' ?>"
                                 class="direct-chat-img initials-image">&nbsp
                            <a href="PersonView.php?PersonID=<?= $per->getPersonId() ?>"><?= $sPerson ?></a></td>
                        <td><?= date_format($per->getCheckinDate(), SystemConfig::getValue('sDateFormatLong')) ?></td>
                        <td><?= $sCheckinby ?></td>
                        <td><?= date_format($per->getCheckoutDate(), SystemConfig::getValue('sDateFormatLong')) ?></td>
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
                                    <input class="btn btn-danger btn-xs" type="submit" name="DeleteBtn"
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

<script language="javascript" type="text/javascript">
    var perArr;
    $(document).ready(function () {
        $('#checkedinTable').DataTable(window.CRM.plugin.dataTable);
    });

    $(document).ready(function() {
        var $input = $("#child, #adult, #adultout");
        $input.autocomplete({
            source: function (request, response) {
                console.log('empty');
                $.ajax({
                    url: window.CRM.root + '/api/persons/search/'+request.term,
                    dataType: 'json',
                    type: 'GET',
                    success: function (rdata) {
                        console.log(rdata);
                        data = JSON.parse(rdata);
                        if(data.length > 0) {
                            data = data[0];
                            response($.map(data.persons, function (item) {
                                var val=item.displayName + (item.role ? '- (' + item.role + ')':'');
                                return {
                                    value: val,
                                    id: item.id,
                                    obj:item
                                }
                            }));
                        }

                    }
                })
            },
            minLength: 2,
            select: function(event,ui) {
                $('[id=' + event.target.id + ']' ).val(ui.item.value);
                $('[id=' + event.target.id + '-id]').val(ui.item.id);
                SetPersonHtml($('#' + event.target.id + 'Details'),ui.item.obj);
            }
        });

    });

    function SetPersonHtml(element, perArr) {
        if(perArr) {
            element.html(
                '<div class="text-center">' +
                '<a target="_top" href="PersonView.php?PersonID=' + perArr.id + '"><h4>' + perArr.displayName + '</h4></a>' +
                '<div class="">' + perArr.familyRole + '</div>' +
                '<div class="text-center">' + perArr.address + '</div>' +
                '<img data-name="' + perArr.displayName + '" data-src="' + window.CRM.root + '/api/persons/' + perArr.id + '/thumbnail" ' +
                'class="initials-image profile-user-img img-responsive img-circle"> </div>'
            );
            element.removeClass('hidden');
            $(".initials-image").initial();
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
    $familyRole="(";
    if ($person->getFamId()) {
        if ($person->getFamilyRole()) {
            $familyRole .= $person->getFamilyRoleName();
        } else {
            $familyRole .=  gettext('Member');
        }
        $familyRole .= gettext(' of the').' <a href="FamilyView.php?FamilyID='. $person->getFamId().'">'.$person->getFamily()->getName().'</a> '.gettext('family').' )';
    } else {
        $familyRole = gettext('(No assigned family)');
    }


    $html = '<div class="text-center">' .
        '<a target="_top" href="PersonView.php?PersonID=' . $iPersonID . '"><h4>' . $person->getTitle(). ' ' . $person->getFullName() . '</h4></a>' .
        '<div class="">' . $familyRole . '</div>' .
        '<div class="text-center">' . $person->getAddress() . '</div>' .
        '<img data-name="' . $person->getFullName() . '" data-src="' . SystemURLs::getRootPath() . '/api/persons/' . $iPersonID . '/thumbnail" ' .
        'class="initials-image profile-user-img img-responsive img-circle"> </div>';
    echo $html;
}
?>
