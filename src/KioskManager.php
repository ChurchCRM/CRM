<?php

/*******************************************************************************
 *
 *  filename    : Dashboard.php
 *  last change : 2014-11-29
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2014
  *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext('Kiosk Manager');

require 'Include/Header.php';
?>
<div class="row">
  <div class="col-lg-4 col-md-2 col-sm-2">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= gettext('Kiosk Manager') ?></h3>
      </div>
      <div class="card-body">
        <div class="col-sm-4">
          <b><?= gettext('Enable New Kiosk Registration') ?>:</b>
          <input data-width="150" id="isNewKioskRegistrationActive" type="checkbox" data-toggle="toggle" data-on="<?= gettext('Active') ?>" data-off="<?= gettext('Inactive') ?>">
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= gettext('Active Kiosks') ?></h3>
      </div>
      <div class="card-body">
        <table id="KioskTable" style="width:100%">
        </table>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">

  function renderKioskAssignment(data) {
    if (data.Accepted) {
      var options ='<option value="None">None</option>';
      var currentAssignment = data.KioskAssignments[0];
      if (window.CRM?.events?.futureEvents) {
          for (var i = 0; i < window.CRM.events.futureEvents.length; i++) {
              var event = window.CRM.events.futureEvents[i];
              if (currentAssignment?.EventId === event.Id) {
                  options += '<option selected value="1-' + event.Id + '">Event - ' + event.Title + '</option>';
              } else {
                  options += '<option value="1-' + event.Id + '">Event - ' + event.Title + '</option>';
              }
          }
      }

        return '<select class="assignmentMenu" data-kioskid="'+data.Id+'">'+ options +'</select>';
    } else {
        return "Kiosk must be accepted";
    }
  }

  $('#isNewKioskRegistrationActive').change(function() {
    if ($("#isNewKioskRegistrationActive").prop('checked')) {
      window.CRM.kiosks.enableRegistration().done(function(data) {
       window.CRM.secondsLeft = moment(data.visibleUntil.date).unix() - moment().unix();
       window.CRM.discoverInterval = setInterval(function(){
         window.CRM.secondsLeft -= 1;
         if (window.CRM.secondsLeft > 0) {
            $("#isNewKioskRegistrationActive").next(".toggle-group").children(".toggle-on").html("Active for "+window.CRM.secondsLeft+" seconds");
         }
         else {
           clearInterval(window.CRM.discoverInterval);
           $('#isNewKioskRegistrationActive').bootstrapToggle('off');
         }
       },1000)
     });
    }

  })


  $(document).on("change", ".assignmentMenu", function(event) {
    var kioskId = $(event.currentTarget).data("kioskid");
    var selected = $(event.currentTarget).val();
    window.CRM.kiosks.setAssignment(kioskId, selected);
  })

  $(document).ready(function(){

    var dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/kiosks/",
      dataSrc: "KioskDevices",
      statusCode: {
          401: function (xhr, error, thrown) {
              window.location = window.location.origin + '/session/begin?location=' + window.location.pathname;
              return false
          }
      }
    },
    columns: [
      {
        width: 'auto',
        title: 'Id',
        data: 'Id',
        searchable: false
      },
      {
        width: 'auto',
        title: 'Kiosk Name',
        data: 'Name',
      },
      {
        width: 'auto',
        title: 'Assignment',
        data: function (row, type, set, meta) {
          if ((row?.KioskAssignments ?? []).length > 0) {
            return row.KioskAssignments[0];
          } else {
            return "None";
          }

        },
        render: function (data, type, full, meta) {
          return renderKioskAssignment(full);
        }

      },
      {
        width: 'auto',
        title: 'Last Heartbeat',
        data: 'LastHeartbeat',
        render: function (data, type, full, meta) {
          return moment(full.LastHeartbeat).fromNow();
        }
      },
      {
        width: 'auto',
        title: 'Accepted',
        data: 'Accepted',
        render: function (data, type, full, meta) {
          if (full.Accepted) {
            return "True";
          } else {
            return "False";
          }

        }
      },
      {
        width: 'auto',
        title: 'Actions',
        render: function (data, type, full, meta) {
          buttons = "<button class='reload' onclick='window.CRM.kiosks.reload("+full.Id+")' >Reload</button>" +
                 "<button class='identify' onclick='window.CRM.kiosks.identify("+full.Id+")' >Identify</button>";
          if(!full.Accepted){
              buttons += "<button class='accept' onclick='window.CRM.kiosks.accept("+full.Id+")' >Accept</button>";
          }
          return buttons;
        }
      }
    ]
  }

    $.extend(dataTableConfig, window.CRM.plugin.dataTable);

    window.CRM.kioskDataTable = $("#KioskTable").DataTable(dataTableConfig)

    setInterval(function(){window.CRM.kioskDataTable.ajax.reload()},5000);
  })

</script>

<?php

require 'Include/Footer.php';
?>
