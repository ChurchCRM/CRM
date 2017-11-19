<?php

/*******************************************************************************
 *
 *  filename    : calendar.php
 *  last change : 2017-11-16
 *  description : manage the full calendar
 *
 *  http://www.churchcrm.io/
 *  Copyright 2017 Logel Philippe
  *
 ******************************************************************************/
 
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Service\CalendarService;
use ChurchCRM\GroupQuery;
use ChurchCRM\EventTypesQuery;

$calenderService = new CalendarService();
use ChurchCRM\dto\SystemURLs;

$groups = GroupQuery::Create()
      ->orderByName()
      ->find();
      

$eventTypes = EventTypesQuery::Create()
      ->orderByName()
      ->find();
  

// Set the page title and include HTML header
$sPageTitle = gettext('Church Calendar');
require 'Include/Header.php'; ?>

<style>
    @media print {
        a[href]:after {
            content: none !important;
        }
    }
    .fc-other-month .fc-day-number {
      display:none;
    }
</style>
<div class="col">
    <div class="box box-primary">
        <div class="box-body">
            <?php foreach ($calenderService->getEventTypes() as $type) {
    ?>
                <div class="col-xs-3 fc-event-container fc-day-grid-event"
                     style="background-color:<?= $type['backgroundColor'] ?>;border-color:<?= $type['backgroundColor'] ?>;color: white; ">
                    <div class="fc-title"><?= gettext($type['Name']) ?></div>
                </div>
                <?php
} ?>
        </div>
    </div>
</div>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?= gettext('Quick Settings') ?></h3>
  </div>
  <div class="box-body">
      <form>
          <div class="col-sm-4"> <b><?= gettext("Birthdate") ?>:</b> <input data-size="small" id="isBirthdateActive" type="checkbox" data-toggle="toggle" data-on="<?= gettext("Include") ?>" data-off="<?= gettext("Exclude") ?>"> </div>
          <div class="col-sm-4"> <b><?= gettext("Anniversary") ?>:</b> <input data-size="small" id="isAnniversaryActive" type="checkbox" data-toggle="toggle" data-on="<?= gettext("Include") ?>" data-off="<?= gettext("Exclude") ?>"></div>
          <div class="col-sm-4"> <b><?= gettext("With Limit") ?>:</b> <input data-size="small" id="isWithLimit" type="checkbox" data-toggle="toggle" data-on="<?= gettext("Include") ?>" data-off="<?= gettext("Exclude") ?>"></div>
      </form>
  </div>
</div>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?= gettext('Filter Settings') ?></h3>
  </div>
  <div class="box-body">
      <form>
          <div class="col-sm-3"> <b><?= gettext("Event Type Filter") ?> : </b> 
            <select type="text" id="EventTypeFilter" value="0">
              <option value='0' ><?= gettext("None") ?></option>
            <?php
                  foreach ($eventTypes as $eventType) {
                      echo "+\"<option value='".$eventType->getID()."'>".$eventType->getName()."</option>\"";
                  }
            ?>
            </select>
          </div>
          <div class="col-sm-6"> <b><?= gettext("Event Group Filter") ?>:</b> 
            <select type="text" id="EventGroupFilter" value="0">
              <option value='0' ><?= gettext("None") ?></option>
            <?php
                  foreach ($groups as $group) {
                      echo "+\"<option value='".$group->getID()."'>".$group->getName()."</option>\"";
                  }
                ?>  
            </select>
          </div>
      </form>
  </div>
</div>

<div class="col">
    <div class="box box-info">
        <div class="box-body no-padding">
            <!-- THE CALENDAR -->
            <div id="calendar"></div>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /. box -->
</div>
<!-- /.col -->

&nbsp;

<!-- fullCalendar 2.2.5 -->
<script>

  var anniversary = true;
  var birthday    = true;
  var withlimit   = false;
  var isModifiable  = <?php 
    if ($_SESSION['bAddEvent']) {
        echo "true";
    } else {
        echo "false";
    }
  ?>;
  
 
  var birthD = localStorage.getItem("birthday");
  if (birthD != null)
  {
    if (birthD == 'checked')
      birthday=true;
    else 
      birthday=false;
      
    $('#isBirthdateActive').prop('checked', birthday);
  }

  var ann = localStorage.getItem("anniversary");
  if (ann != null)
  {
    if (ann == 'checked')
      anniversary=true;
    else 
      anniversary=false;
    $('#isAnniversaryActive').prop('checked', anniversary);
  }
  
  var wLimit = localStorage.getItem("withlimit");
  if (wLimit != null)
  {
    if (wLimit == 'checked')
      withlimit=true;
    $('#isWithLimit').prop('checked', withlimit);
  }  
  
  
  $("#isBirthdateActive").on('change',function () {
     var _val = $(this).is(':checked') ? 'checked' : 'unchecked';
     
     if (_val == 'checked'){
       birthday = true;
     } else { 
      birthday = false;
     }
     $('#calendar').fullCalendar( 'refetchEvents' );
     
     localStorage.setItem("birthday",_val);     
  });
  
  $("#isAnniversaryActive").on('change',function () {
     var _val = $(this).is(':checked') ? 'checked' : 'unchecked';
     if (_val == 'checked'){
      anniversary = true;
     } else { 
      anniversary = false;
     }

     $('#calendar').fullCalendar( 'refetchEvents' );
     
     localStorage.setItem("anniversary",_val); 
  });
  
  $("#isWithLimit").on('change',function () {
     var _val = $(this).is(':checked') ? 'checked' : 'unchecked';
     if (_val == 'checked'){
        withlimit = true;
     } else { 
        withlimit = false;
     }
   
     var options = $('#calendar').fullCalendar('getView').options;
     options.eventLimit = withlimit;
     $('#calendar').fullCalendar('destroy');
     $('#calendar').fullCalendar(options);
     
     localStorage.setItem("withlimit",_val); 
  });
  
  window.groupFilterID     = 0;
  window.EventTypeFilterID = 0;
  
  localStorage.setItem("groupFilterID",groupFilterID);
  localStorage.setItem("EventTypeFilterID",EventTypeFilterID);  
  
  $("#EventGroupFilter").on('change',function () {
     var e = document.getElementById("EventGroupFilter");
     window.groupFilterID = e.options[e.selectedIndex].value;
   
    $('#calendar').fullCalendar( 'refetchEvents' );
    
    if (window.groupFilterID == 0)
      $("#ATTENDENCES").parents("tr").hide();
     
     localStorage.setItem("groupFilterID",groupFilterID); 
  });
  
  
  $("#EventTypeFilter").on('change',function () {
     var e = document.getElementById("EventTypeFilter");
     window.EventTypeFilterID = e.options[e.selectedIndex].value;
      
     $('#calendar').fullCalendar( 'refetchEvents' );
     
     localStorage.setItem("EventTypeFilterID",EventTypeFilterID); 
  });
  
  // I have to do this because EventGroup isn't yet present when you load the page the first time
  $(document).on('change','#EventGroup',function () {
     var e = document.getElementById("EventGroup");
     var _val = e.options[e.selectedIndex].value;
   
    if (_val == 0)
      $("#ATTENDENCES").parents("tr").hide();
    else
      $("#ATTENDENCES").parents("tr").show();
     
     localStorage.setItem("groupFilterID",groupFilterID); 
  });
  
  function deleteText(a)
  {
    if(a.value=='<?= gettext("Calendar Title")?>' || a.value=='<?= gettext("Calendar description")?>'){ a.value=''; a.style.color='#000';};
  }
  
  function BootboxContent(){    
    var frm_str = '<form id="some-form">'
       + '<table class="table">'
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span><?= gettext('Select your event type'); ?></td>"
              +'<td colspan="3" class="TextColumn">'
              +'<select type="text" id="eventType" value="39">'
                   //+"<option value='0' ><?= gettext("Personal") ?></option>"
                   <?php
                      foreach ($eventTypes as $eventType) {
                          echo "+\"<option value='".$eventType->getID()."'>".$eventType->getName()."</option>\"";
                      }
                    ?>
                +'</select>'
              +'</td>'
            +'</tr>'
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span><?= gettext('Event Title') ?> :</td>"
              +'<td colspan="1" class="TextColumn">'
                +"<input type='text' id='EventTitle' onfocus='deleteText(this)' value='<?= gettext("Calendar Title")?>' size='30' maxlength='100' class='form-control' required>"
              +'</td>'
            +'</tr>'
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span><?= gettext('Event Desc') ?>:</td>"
              +'<td colspan="3" class="TextColumn">'
                +"<textarea id='EventDesc' rows='4' maxlength='100' onfocus='deleteText(this)' class='form-control' required><?= gettext("Calendar description")?></textarea>"
              +'</td>'
            +'</tr>'          
            +'<tr>'
              +"<td class='LabelColumn'><span style='color: red'>*</span><?= gettext('Event Group') ?>:</td>"
              +'<td class="TextColumn">'
                +'<select type="text" id="EventGroup" value="39">'
                   +"<option value='0' Selected><?= gettext("None") ?></option>"
                <?php
                  foreach ($groups as $group) {
                      echo "+\"<option value='".$group->getID()."'>".$group->getName()."</option>\"";
                  }
                ?>              
                +'</select>'
              +'</td>'
            +'</tr>'
            +'<tr>'
              +"<td class='LabelColumn' id='ATTENDENCES'><?= gettext('Attendance Counts') ?></td>"
              +'<td class="TextColumn" colspan="3">'
                +'<table>'
                +'<tr>'
                    +"<td><strong><?= gettext("Total")?>:&nbsp;</strong></td>"
                  +'<td>'
                  +'<input type="text" id="Total" value="0" size="8" class="form-control">'
                  +'</td>'
                  +'</tr>'
                +'<tr>'
                    +"<td><strong><?= gettext("Members")?>:&nbsp;</strong></td>"
                  +'<td>'
                  +'<input type="text" id="Members" value="0" size="8" class="form-control">'
                 +' </td>'
                  +'</tr>'
               +' <tr>'
                    +"<td><strong><?= gettext("Visitors")?>:&nbsp;</strong></td>"
                  +'<td>'
                  +'<input type="text" id="Visitors" value="0" size="8" class="form-control">'
                  +'</td>'
                  +'</tr>'
                      +'<tr>'
                +"<td><strong><?= gettext('Attendance Notes: ') ?> &nbsp;</strong></td>"
                  +'<td><input type="text" id="EventCountNotes" value="" class="form-control">'
                  +'</td>'
                  +'</tr>'
                  +'</table>'
                      +'</td>'
            +'</tr>'
            +'<tr>'
              +"<td class='LabelColumn'><?= gettext('Event Sermon') ?>:</td>"
              +'<td colspan="3" class="TextColumn"><textarea name="EventText" rows="5" cols="80" class="form-control" id="eventPredication"></textarea></td>'
            +'</tr>'
            //+'<tr>'
              //+'<td class="LabelColumn"><span style="color: red">*</span>Statut de l&#39;événement:</td>'
              //+'<td colspan="3" class="TextColumn">'
                //+'<input type="radio" name="EventStatus" value="0" checked/> Actif      <input type="radio" name="EventStatus" value="1" /> Inactif    </td>'
            //+'</tr>'
          +'</table>'
       + '</form>';

        var object = $('<div/>').html(frm_str).contents();

        return object
    }

  
    $(document).ready(function () {
        //
        // initialize the calendar
        // -----------------------------------------------------------------
        $('#calendar').fullCalendar({
          header: {
              left: 'prev,next today',
              center: 'title',
              right: 'month,agendaWeek,agendaDay,listMonth'
          },
          height: 500,
          selectable: isModifiable,
          editable:isModifiable,
          eventDrop: function(event, delta, revertFunc) {
            if (event.type == 'event'){
              bootbox.confirm({
               title: "<?= gettext("Move Event") ?>?",
                message: "<?= gettext("Are you sure about this change?") ?>\n"+event.title + " <?= gettext("will be dropped.") ?>",
                buttons: {
                  cancel: {
                    label: '<i class="fa fa-times"></i> <?= gettext("Cancel") ?>'
                  },
                  confirm: {
                    label: '<i class="fa fa-check"></i> <?= gettext("Confirm") ?>'
                  }
                },
                callback: function (result) {
                 if (result == true)// only event can be drag and drop, not anniversary or birthday
                 {
                  window.CRM.APIRequest({
                   method: 'POST',
                   path: 'events/',
                   data: JSON.stringify({"evntAction":'moveEvent',"eventID":event.eventID,"start":event.start.format()})
                  }).done(function(data) {
                     // now we can create the event
                     $('#calendar').fullCalendar('removeEvents',event._id);// delete old one
                     $('#calendar').fullCalendar('renderEvent', data, true); // add the new one
                     $('#calendar').fullCalendar('unselect'); 
                  });
                 } else {
                  revertFunc();
                 }
                 console.log('This was logged in the callback: ' + result);
                }        
            });
           } else {
            revertFunc();
           }
        },
        eventResize: function(event, delta, revertFunc) {
          if (event.type == 'event'){
            bootbox.confirm({
             title: "<?= gettext("Resize Event") ?>?",
              message: "<?= gettext("Are you sure about this change?") ?>\n"+event.title + " <?= gettext("will be dropped.") ?>",
              buttons: {
                cancel: {
                  label: '<i class="fa fa-times"></i> <?= gettext("Cancel") ?>'
                },
                confirm: {
                  label: '<i class="fa fa-check"></i> <?= gettext("Confirm") ?>'
                }
              },
              callback: function (result) {
               if (result == true)// only event can be drag and drop, not anniversary or birthday
               {
                window.CRM.APIRequest({
                 method: 'POST',
                 path: 'events/',
                 data: JSON.stringify({"evntAction":'resizeEvent',"eventID":event.eventID,"end":event.end.format()})
                }).done(function(data) {
                   // now we can create the event
                   $('#calendar').fullCalendar('removeEvents',event._id);// delete old one
                   $('#calendar').fullCalendar('renderEvent', data, true); // add the new one
                   $('#calendar').fullCalendar('unselect'); 
                });
               } else {
                revertFunc();
               }
               console.log('This was logged in the callback: ' + result);
              }        
          });
         } else {
          revertFunc();
         }
      },
      selectHelper: true,        
      select: function(start, end) {        
       var modal = bootbox.dialog({
         message: BootboxContent,
         title: "<?= gettext("Event Creation") ?>",
         buttons: [
          {
           label: "<?= gettext("Save") ?>",
           className: "btn btn-primary pull-left",
           callback: function() {
              var e = document.getElementById("eventType");
              var eventTypeID = e.options[e.selectedIndex].value;
                                         
              var EventTitle =  $('form #EventTitle').val();
              var EventDesc =  $('form #EventDesc').val();
                             
              var e = document.getElementById("EventGroup");
              var EventGroupID = e.options[e.selectedIndex].value;
                             
              var Total =  $('form #Total').val();
              var Members = $('form #Members').val();
              var Visitors = $('form #Visitors').val();
              var EventCountNotes = $('form #EventCountNotes').val();
                             
              var eventPredication = $('form #eventPredication').val();
            
              var add = false;
                                                            
              window.CRM.APIRequest({
                    method: 'POST',
                    path: 'events/',
                    data: JSON.stringify({"evntAction":'createEvent',"eventTypeID":eventTypeID,"EventTitle":EventTitle,"EventDesc":EventDesc,"EventGroupID":EventGroupID,"Total":Total,"Members":Members,"Visitors":Visitors,"EventCountNotes":EventCountNotes,"eventPredication":eventPredication,"start":moment(start).format(),"end":moment(end).format()})
              }).done(function(data) {                   
                $('#calendar').fullCalendar('renderEvent', data, true); // stick? = true             
                $('#calendar').fullCalendar('unselect');              
                add = true;              
                modal.modal("hide");   
                
                var box = bootbox.dialog({message : "<?=gettext("Event was added successfully.") ?>"});
                
                setTimeout(function() {
                    // be careful not to call box.hide() here, which will invoke jQuery's hide method
                    box.modal('hide');
                }, 3000);
                return true;
              });

              return add;      
            }
          },
          {
           label: "<?= gettext("Close") ?>",
           className: "btn btn-default pull-left",
           callback: function() {
              console.log("just do something on close");
           }
          }
         ],
         show: false,
         onEscape: function() {
            modal.modal("hide");
         }
       });
  
       modal.modal("show");
       $("#ATTENDENCES").parents("tr").hide();
      },
      eventLimit: withlimit, // allow "more" link when too many events
      locale: window.CRM.lang,
      events: window.CRM.root + '/api/calendar/events',
      eventRender: function (event, element, view) {
        groupFilterID = window.groupFilterID;
        EventTypeFilterID = window.EventTypeFilterID;
        
        if (event.hasOwnProperty('type')){
          if (event.type == 'event' 
            && (groupFilterID == 0 || (groupFilterID>0 && groupFilterID == event.groupID)) 
            && (EventTypeFilterID == 0 || (EventTypeFilterID>0 && EventTypeFilterID == event.eventTypeID))){
            return true;
          } else if(event.type == 'event' 
            && ((groupFilterID>0 && groupFilterID != event.groupID)
                || (EventTypeFilterID>0 && EventTypeFilterID != event.eventTypeID))){
            return false;
          } else if ((event.allDay || event.type != 'event')){// we are in a allDay event          
           if (event.type == 'anniversary' && anniversary == true || event.type == 'birthday' && birthday == true){
            var evStart = moment(view.intervalStart).subtract(1, 'days');
            var evEnd = moment(view.intervalEnd).subtract(1, 'days');
            if (!event.start.isAfter(evStart) || event.start.isAfter(evEnd)) {
              return false;
            }
           } else {
            return false;
           }
          }
         }
      }
    });
  });

</script>

<?php require 'Include/Footer.php'; ?>