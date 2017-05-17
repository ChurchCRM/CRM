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
  
   $(function() {
    $('#isNewKioskRegistrationActive').change(function() {
      if ($(this).prop('checked')){
        window.CRM.secondsLeft = 5;
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
      }
    })
  })
  
  $(document).ready(function(){
    $("#KioskTable").DataTable({
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
        title: 'Device Type',
        data: 'DeviceType'
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
          return "<button class='reload' onclick='window.CRM.reloadKiosk("+full.Id+")' >Reload</button>";

        }
      }
    ]
  })
  })
  
</script>

<?php

require 'Include/Footer.php';
?>
