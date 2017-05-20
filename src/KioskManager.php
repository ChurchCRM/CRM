<?php
/*******************************************************************************
 *
 *  filename    : Dashboard.php
 *  last change : 2014-11-29
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2014
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';


//Set the page title
$sPageTitle = gettext('Kiosk Manager');

require 'Include/Header.php';
?>
<div class="row">
  <div class="col-lg-4 col-md-2 col-sm-2">
    <div class="box">
      <div class="box-header">
        <h3 class="box-title"><?= gettext('Kiosk Manager') ?></h3>
      </div>
      <div class="box-body">
        <div class="col-sm-4"> 
          <b><?= gettext('Enable New Kiosk Registration') ?>:</b> 
          <input data-width="150" id="isNewKioskRegistrationActive" type="checkbox" data-toggle="toggle" data-on="<?= gettext('Active') ?>" data-off="<?= gettext('Inactive') ?>">
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-12">
    <div class="box">
      <div class="box-header">
        <h3 class="box-title"><?= gettext('Active Kiosks') ?></h3>
      </div>
      <div class="box-body">
        <table id="KioskTable" style="width:100%">
          
        </table>
       
      </div>
    </div>
  </div>
</div>

<script>
  
  window.CRM.APIRequest = function(options) {
      if (!options.method)
      {
        options.method="GET"
      }
      options.url=window.CRM.root+"/api/"+options.path;
      options.dataType = 'json';
      options.contentType =  "application/json";
      return $.ajax(options);
    }
    
  window.CRM.reloadKiosk = function(id)
  {
    window.CRM.APIRequest({
      "path":"kiosks/"+id+"/reloadKiosk",
      "method":"POST"
    }).done(function(data){
      console.log(data);
    })
  }
  window.CRM.enableKioskRegistration = function() {
    return window.CRM.APIRequest({
      "path":"kiosks/allowRegistration",
      "method":"POST"
    })  
  }
  
  window.CRM.acceptKiosk = function (id)
  {
     window.CRM.APIRequest({
      "path":"kiosks/"+id+"/acceptKiosk",
      "method":"POST"
    }).done(function(data){
      window.CRM.kioskDataTable.ajax.reload()
    })
  }
  
  window.CRM.identifyKiosk = function (id)
  {
     window.CRM.APIRequest({
      "path":"kiosks/"+id+"/identifyKiosk",
      "method":"POST"
    }).done(function(data){
      console.log(data);
    })
  }
  
  window.CRM.setKioskAssignment = function (id,assignmentId)
  {
    console.log(assignmentId);
    assignmentSplit = assignmentId.split("-");
    if(assignmentSplit.length > 0)
    {
      assignmentType = assignmentSplit[0];
      eventId = assignmentSplit[1];
    }
    else
    {
      assignmentType = assignmentId;
    }
   
     window.CRM.APIRequest({
      "path":"kiosks/"+id+"/setAssignment",
      "method":"POST",
      "data":JSON.stringify({"assignmentType":assignmentType,"eventId":eventId})
    }).done(function(data){
      console.log(data);
    })
  }
  
  window.CRM.renderAssignment = function(data) {
    if (data.EventId !== 0)
    {
       return '<option value="'+data.AssignmentType+'-'+data.EventId+'">'+data.AssignmentType+'-'+data.EventId+'</option>';
    }
    else
    {
      return '<option value="'+data.AssignmentType+'">'+data.AssignmentType+'</option>';

    }
      
  }
  
  window.CRM.getFutureEventes = function()
  {
    window.CRM.APIRequest({
      "path":"events/notDone"
    }).done(function(data){
      window.CRM.futureEvents=data.Events;
    });
  }
  
  window.CRM.GetAssignmentOptions = function() {
    console.log(window.CRM.futureEvents.length);
    //var options = '<option value="None">None</option><option value="2">Self Registration</option>';
    var options ='<option value="None">None</option>';
    for (var i=0; i < window.CRM.futureEvents.length; i++)
    {
      var event = window.CRM.futureEvents[i];
      options += '<option value="1-'+event.Id+'">Event - '+event.Title+'</option>'
    }
    return options;
  }
  
  
  $('#isNewKioskRegistrationActive').change(function() {
    if ($("#isNewKioskRegistrationActive").prop('checked')){
      window.CRM.enableKioskRegistration().done(function(data) {
      console.log(data);
       window.CRM.secondsLeft = moment(data.visibleUntil.date).unix() - moment().unix();
       console.log(window.CRM.secondsLeft);
       window.CRM.discoverInterval = setInterval(function(){
         window.CRM.secondsLeft-=1;
         if (window.CRM.secondsLeft > 0)
         {
            $("#isNewKioskRegistrationActive").next(".toggle-group").children(".toggle-on").html("Active for "+window.CRM.secondsLeft+" seconds");
         }
         else
         {
           clearInterval(window.CRM.discoverInterval);
           $('#isNewKioskRegistrationActive').bootstrapToggle('off');
         }

       },1000)
     });
    }

  })
  
  window.CRM.getFutureEventes();
 
  $(document).on("change",".assignmentMenu",function(event){
    var kioskId = $(event.currentTarget).data("kioskid");
    var selected = $(event.currentTarget).val();
    window.CRM.setKioskAssignment(kioskId,selected);
    console.log(kioskId+" " + selected);
  })
  
  $(document).ready(function(){
    window.CRM.kioskDataTable = $("#KioskTable").DataTable({
    "language": {
      "url": window.CRM.root + "/skin/locale/datatables/" + window.CRM.locale + ".json"
    },
    responsive: true,
    ajax: {
      url: window.CRM.root + "/api/kiosks/",
      dataSrc: "KioskDevices"
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
        data: function (row,type,set,meta){
          console.log(row);
          if (row.KioskAssignments.length > 0)
          {
            return row.KioskAssignments[0];
          }
          else
          {
            return "None";
          }
            
        },
        render: function (data,type,full,meta)
        {
          if(full.Accepted){
            return '<select class="assignmentMenu" data-kioskid="'+full.Id+'" data-selectedassignment='+data+'>'+window.CRM.renderAssignment(data)+ window.CRM.GetAssignmentOptions() +'</select>';
          }
          else
          {
            return "Kiosk must be accepted";
          }
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
          if (full.Accepted)
          {
            return "True";
          }
          else {
            return "False";
          }

        }
      },
      {
        width: 'auto',
        title: 'Actions',
        render: function (data, type, full, meta) {
          buttons = "<button class='reload' onclick='window.CRM.reloadKiosk("+full.Id+")' >Reload</button>" +
                 "<button class='identify' onclick='window.CRM.identifyKiosk("+full.Id+")' >Identify</button>";
          if(!full.Accepted){
              buttons += "<button class='accept' onclick='window.CRM.acceptKiosk("+full.Id+")' >Accept</button>";
          }
          return buttons;
        }
      }
    ]
  })
  
    setInterval(function(){window.CRM.kioskDataTable.ajax.reload()},5000);
  })
  
</script>

<?php

require 'Include/Footer.php';
?>
