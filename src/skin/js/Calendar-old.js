window.moveEventModal = {
  getButtons: function() {
    return  {
        cancel: {
          label: '<i class="fa fa-times"></i> ' + i18next.t("Cancel")
        },
        confirm: {
          label: '<i class="fa fa-check"></i> ' + i18next.t("Confirm")
        }
    };
  },
  modalCallBack: function(result) {
    if(result === true) {
    window.CRM.APIRequest({
      method: 'POST',
      path: 'events/'+window.moveEventModal.event.id+"/time",
      data: JSON.stringify({"startTime": window.moveEventModal.event.start.format(),"endTime": window.moveEventModal.event.end.format()})
    });
  } else {
     window.moveEventModal.revertFunc();
   }
    
  },
  handleEventDrop: function (event, delta, revertFunc) {
    originalStart = event.start.clone().subtract(delta).format("dddd, MMMM Do YYYY, h:mm:ss a");
    newStart = event.start.format("dddd, MMMM Do YYYY, h:mm:ss a");
    window.moveEventModal.revertFunc = revertFunc;
    window.moveEventModal.event = event;
    bootbox.confirm({
      title: i18next.t("Move Event") + "?",
      message: i18next.t("Are you sure you want to move") +" " + event.title + " " + i18next.t("from") + "<br/>" +
       originalStart + "<br/>" + 
        i18next.t("to") + "<br/>" +  newStart,
      buttons: window.moveEventModal.getButtons(),
      callback: window.moveEventModal.modalCallBack
    });
  },
  handleEventResize: function (event, delta, revertFunc) {
    start = event.start.format("dddd, MMMM Do YYYY, h:mm:ss a");
    originalEnd = event.end.clone().subtract(delta).format("dddd, MMMM Do YYYY, h:mm:ss a");
    newEnd = event.end.format("dddd, MMMM Do YYYY, h:mm:ss a");
    window.moveEventModal.revertFunc = revertFunc;
    window.moveEventModal.event = event;
    bootbox.confirm({
      title: i18next.t("Resize Event") + "?",
      message: i18next.t("Are you sure you want to change the end time for ") +" " + event.title + " " + i18next.t("from") + "<br/>" +
       originalEnd + "<br/>" + 
        i18next.t("to") + "<br/>" +  newEnd,
      buttons: window.moveEventModal.getButtons(),
      callback: window.moveEventModal.modalCallBack
    });
  }
};

window.NewOrEditEventModal = {
  getBootboxContent: function() {
    var frm_str = '<form id="some-form">'
          + '<table class="table modal-table">'
          + '<tr>'
          + "<td class='LabelColumn'><span class='required-field'>*</span>" + i18next.t('Event Type') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<select type="text" id="eventType" value="39"  >'
          + '</select>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span class='required-field'>*</span>" + i18next.t('Event Desc') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + "<textarea id='EventDesc' rows='4' maxlength='100' class='form-control' required >" + i18next.t("Calendar description") + "</textarea>"
          + '</td>'
          + '</tr>'
          + '<tr>'
          + '<td class="LabelColumn"><span class="required-field">*</span>'
          + i18next.t("Date Range") + ':'
          + '</td>'
          + '<td colspan="3" class="TextColumn">'
          + '<input type="text" name="EventDateRange" id="EventDateRange" class="form-control"  required>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span class='required-field'>*</span>" + i18next.t('Pinned Calendars') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<select type="text" multiple="multiple" id="PinnedCalendars" value="39" >'
          + '</select>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn' id='ATTENDENCES'>" + i18next.t('Attendance Counts') + ":</td>"
          + '<td class="TextColumn" colspan="3">'
          + '<table>'
          + '<tr>'
          + "<td><strong>" + i18next.t("Total") + ":&nbsp;</strong></td>"
          + '<td>'
          + '<input type="text" id="Total"  class="form-control"  >'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td><strong>" + i18next.t("Members") + ":&nbsp;</strong></td>"
          + '<td>'
          + '<input type="text" id="Members" value="0" size="8" class="form-control"  >'
          + ' </td>'
          + '</tr>'
          + ' <tr>'
          + "<td><strong>" + i18next.t("Visitors") + ":&nbsp;</strong></td>"
          + '<td>'
          + '<input type="text" id="Visitors" value="0" size="8" class="form-control"  >'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td><strong>" + i18next.t('Attendance Notes: ') + " &nbsp;</strong></td>"
          + '<td><input type="text" id="EventCountNotes" value="" class="form-control">'
          + '</td>'
          + '</tr>'
          + '</table>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + '<td colspan="4" class="TextColumn">' + i18next.t('Event Description') + '<textarea name="EventText" rows="5" cols="80" class="form-control" id="eventPredication"  ></textarea></td>'
          + '</tr>'
          + '</table>'
          + '</form>';
    var object = $('<div/>').html(frm_str).contents();

    return object
  },
  readDOMNewEvent: function() {
    var e = document.getElementById("eventType");
      var eventTypeID = e.options[e.selectedIndex].value;

      var EventTitle = $('#EventTitle').val();
      var EventDesc = $('form #EventDesc').val();

      var eventCalendars = $("#PinnedCalendars").val();

      var Total = $('form #Total').val();
      var Members = $('form #Members').val();
      var Visitors = $('form #Visitors').val();
      var EventCountNotes = $('form #EventCountNotes').val();

      var eventPredication = CKEDITOR.instances['eventPredication'].getData();//$('form #eventPredication').val();
      var dateRange = $('#EventDateRange').val().split(" - ");
      var start = moment(dateRange[0],"YYYY-MM-DD HH:mm:ss a").format();
      var end = moment(dateRange[1],"YYYY-MM-DD HH:mm:ss a").format();
      var add = false;
      return {
          "eventTypeID": eventTypeID,
          "EventTitle": EventTitle,
          "EventDesc": EventDesc,
          "eventCalendars": eventCalendars,
          "Total": Total,
          "Members": Members,
          "Visitors": Visitors,
          "EventCountNotes": EventCountNotes,
          "eventPredication": eventPredication,
          "start": start,
          "end": end
        };
  },
  saveButtonCallback: function() {
    var Event = window.NewOrEditEventModal.readDOMNewEvent();
      window.CRM.APIRequest({
        method: 'POST',
        path: 'events',
        data: JSON.stringify(Event)
      }).done(function (data) {
        window.CRM.refreshAllFullCalendarSources();
        window.NewOrEditEventModal.modal.modal("hide");
      });
  },  
  getSaveButton: function() {
    return {
      label: i18next.t("Save"),
      className: "btn btn-primary pull-right",
      callback: window.NewOrEditEventModal.saveButtonCallback
    };
  },
  getCancelButton: function() {
    return {
      label: i18next.t("Cancel"),
      className: "btn btn-default pull-left"
    };
  },
  loadCalendars: function() {
    window.CRM.APIRequest({
      method: 'GET',
      path: 'calendars',
    }).done(function (data) {
      var calendars = data.Calendars;
      var elt = document.getElementById("PinnedCalendars");
      var len = calendars.length;
      
      for (i = 0; i < len; ++i) {
        var option = document.createElement("option");
        // there is a groups.type in function of the new plan of schema
        option.text = calendars[i].Name;
        option.value = calendars[i].Id;
        elt.appendChild(option);
      }
      

    });
  },
  loadEventTypes: function() {
    window.CRM.APIRequest({
    method: 'GET',
    path: 'events/types',
  }).done(function (data) {
    eventTypes=data.EventTypes;
    var elt = document.getElementById("eventType");
    var len = eventTypes.length;

    for (i = 0; i < len; ++i) {
      var option = document.createElement("option");
      option.text = eventTypes[i].Name;
      option.value = eventTypes[i].Id;
      elt.appendChild(option);
    }

  });
  },
  configureModalUIElements: function(start,end) {
    // we add the calendars
    window.NewOrEditEventModal.loadCalendars();
    window.NewOrEditEventModal.loadEventTypes();
    if (!start.hasTime())
    {
      start.hour(8);
      start.minute(0);
      end.hour(8);
      end.minute(30);
      end.day(end.day() - 1);
    }
    $('#EventDateRange').daterangepicker({
      timePicker: true,
      timePickerIncrement: 15,
      linkedCalendars: true,
      showDropdowns: true,
      locale: {
        format: 'YYYY-MM-DD h:mm A'
      },
      minDate: 1 / 1 / 1900,
      startDate: start.format("YYYY-MM-DD h:mm A"),
      endDate: end.format("YYYY-MM-DD h:mm A")
    });
    $("#PinnedCalendars").select2({ width: '100%' });

    // this will ensure that image and table can be focused
    $(document).on('focusin', function (e) {
      e.stopImmediatePropagation();
    });

    $('#EventTitle').on('click', function () {
      if (this.defaultValue == i18next.t("Event Title")) {
        this.defaultValue = '';
        this.style.color = '#000';
      }
      ;
    });

    $('#EventDesc').on('click', function () {
      if (this.defaultValue == i18next.t("Calendar description")) {
        this.defaultValue = '';
        this.style.color = '#000';
      }
      ;
    });

    // this will create the toolbar for the textarea
    CKEDITOR.replace('eventPredication', {
      customConfig: window.CRM.root + '/skin/js/ckeditor/calendar_event_editor_config.js',
      language: window.CRM.lang,
      width: '100%'
    });

    $("#ATTENDENCES").parents("tr").hide();
  },
  configureEditModalUIElements: function(event) {
   $("#PinnedCalendars").val([1]);
   //todo: make this work!
  },
  getNewEventModal: function(start,end) {
    window.NewOrEditEventModal.mode='new';
    window.NewOrEditEventModal.modal = bootbox.dialog({
      message: window.NewOrEditEventModal.getBootboxContent,
      title: "<input type='text' id='EventTitle' value='" + i18next.t("Event Title") + "' size='30' maxlength='100' class='form-control' required>",
      buttons: [
        window.NewOrEditEventModal.getSaveButton(),
        window.NewOrEditEventModal.getCancelButton()
      ],
      show: false,
      onEscape: function () {
        window.NewOrEditEventModal.modal.modal("hide");
      }
    });
    window.NewOrEditEventModal.modal.modal("show");
    window.NewOrEditEventModal.configureModalUIElements(start,end);
  },
  getEditEventModal: function(event) {
    window.NewOrEditEventModal.mode='edit';
    window.getEventDataFromFullCalendar(event).done(function (data) {
      var event = data.Events[0];
      window.NewOrEditEventModal.modal = bootbox.dialog({
        message: window.NewOrEditEventModal.getBootboxContent,
        title: "<input type='text' id='EventTitle' value='" + event.Title + "' size='30' maxlength='100' class='form-control'   required>",
        buttons: [
          window.NewOrEditEventModal.getSaveButton(),
          window.NewOrEditEventModal.getCancelButton()
        ],
        show: false,
        onEscape: function () {
          window.NewOrEditEventModal.modal.modal("hide");
        }
      });
      window.NewOrEditEventModal.modal.modal("show");
      
      window.NewOrEditEventModal.configureModalUIElements(moment(event.Start),moment(event.End));
      window.NewOrEditEventModal.configureEditModalUIElements(event);
      
    });
  }
};

window.getEventDataFromFullCalendar =  function(fullCalendarEvent) {
   if (fullCalendarEvent.source.url.match(/systemcalendar/g))
    {
      path = "systemcalendars/"+event.source.id+"/events/"+event.id;
    }
    else
    {
      path = "events/"+fullCalendarEvent.id;
    }
    return window.CRM.APIRequest({
      method: 'GET',
      path: path,
    });
};

window.CRM.refreshAllFullCalendarSources = function () {
  $(window.CRM.fullcalendar.fullCalendar("getEventSources")).each(function(idx,obj) {
    window.CRM.fullcalendar.fullCalendar("refetchEventSources", obj);
  });
}

window.displayEventModal = {
  getBootboxContent: function (event){ 
    var calendarPinnings ='';
    $.each(event.CalendarEvents,function (idx,obj) {
      calendarPinnings += "<li>"+obj.Calendar.Name+"</li>";
    });
    var frm_str = '<table class="table modal-table">'
          + '<tr>'
          + "<td><span class='required-field'>*</span>" + i18next.t('Event Type') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<p>' + event.Type + "</p>" 
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span class='required-field'>*</span>" + i18next.t('Event Desc') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<p>' + event.Desc + "</p>" 
          + '</td>'
          + '</tr>'
          + '<tr>'
          + '<td class="LabelColumn"><span class="required-field">*</span>'
          + i18next.t("Date Range") + ':'
          + '</td>'
          + '<td colspan="3" class="TextColumn">'
          + '<p>'+new moment(event.Start).format()+ " - " + new moment(event.End).format() +'</p>' 
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span class='required-field'>*</span>" + i18next.t('Pinned Calendars') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<ul>'
          + calendarPinnings
          + "</li>"
          + '</select>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + '<td colspan="4" class="TextColumn">' + i18next.t('Event Text') + '<p>'+ event.Text + '</p></td>'
          + '</tr>'
          + '</table>'
          + '</form>';
    var object = $('<div/>').html(frm_str).contents();

    return object
  },
  getButtons: function () {
    buttons =  [];
    if (window.CRM.calendarJSArgs.isModifiable) {
      buttons.push({
        label: i18next.t("Edit"),
        className: "btn btn-success",
        callback: function(){
          window.location = window.CRM.root + "/ListEvents.php"
        }
      });
    }
    if (window.CRM.calendarJSArgs.isModifiable) {
      buttons.push({
        label: i18next.t("Delete"),
        className: "btn btn-danger pull-left",
        callback: function() {
          window.CRM.APIRequest({
            method: "DELETE",
            path: "events/"+window.displayEventModal.event.id
          }).done(function() {
            window.CRM.refreshAllFullCalendarSources();
          });
        }
      });
    }
    buttons.push({
      label: i18next.t("Cancel"),
      className: "btn btn-default pull-right"
    });
    return buttons;
  },
  getDisplayEventModal: function(event) {
   window.displayEventModal.event = event;
   window.getEventDataFromFullCalendar(event).done(function (data) {
      var bootboxmessage = window.displayEventModal.getBootboxContent(data.Events[0]);
      window.displayEventModal.modal = bootbox.dialog({
        title: data.Events[0].Title,
        message: bootboxmessage,
        show: true,
        buttons: window.displayEventModal.getButtons(),
        onEscape: function () {
          window.displayEventModal.modal.modal("hide");
        }
      });
    });
  }
}

function deleteCalendar(){
  window.CRM.APIRequest({
      method:"DELETE",
      path: "calendars/"+window.calendarPropertiesModal.calendar.Id
    }).done(function(data){
      var eventSourceURL = window.CRM.root+"/api/calendars/"+$(this).data("calendarid")+"/fullcalendar";
      window.CRM.fullcalendar.fullCalendar("removeEventSource",{id: window.calendarPropertiesModal.calendar.Id, url: eventSourceURL});
      initializeFilterSettings();
    });
}

window.calendarPropertiesModal = {
  getBootboxContent: function (calendar){ 
    var HTMLURL = '';
    var icsURL = '';
    var jsonURL = '';
    if (calendar.AccessToken){
      HTMLURL =  window.CRM.fullURL + "external/calendars/"+calendar.AccessToken;
      icsURL =  window.CRM.fullURL + "api/public/calendar/"+calendar.AccessToken+"/ics";
      jsonURL =  window.CRM.fullURL + "api/public/calendar/"+calendar.AccessToken+"/events";
    }
    var frm_str = '<form id="some-form"><table class="table modal-table">'
          + '<tr>'
          + "<td>" + i18next.t('Access Token') + ":</td>"
          + '<td colspan="3">'
          + '<input id="AccessToken" class="form-control" type="text" readonly value="' + calendar.AccessToken + '"/>'
          + (window.CRM.calendarJSArgs.isModifiable ? '<a id="NewAccessToken" class="btn btn-warning"><i class="fa fa-repeat"></i>' + i18next.t("New Access Token") + '</a>' :  '')
          + (window.CRM.calendarJSArgs.isModifiable && calendar.AccessToken != null ? '<a id="DeleteAccessToken" class="btn btn-danger"><i class="fa fa-trash-o"></i>' + i18next.t("Delete Access Token") + '</a>' :  '')
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'>" + i18next.t('HTML URL') + ":</td>"
          + '<td colspan="3">'
          + '<span ><a href="'+HTMLURL+'">'+HTMLURL+'</a></span>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'>" + i18next.t('ICS URL') + ":</td>"
          + '<td colspan="3">'
          + '<span ><a href="'+icsURL+'">'+icsURL+'</a></span>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'>" + i18next.t('JSON URL') + ":</td>"
          + '<td colspan="3">'
          + '<span ><a href="'+jsonURL+'">'+jsonURL+'</a></span>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'>" + i18next.t('Foreground Color') + ":</td>"
          + '<td >'
          + '<p>' + calendar.ForegroundColor + "</p>" 
          + '</td>'
          + '</tr>'
          + '<tr>'
          + '<td class="LabelColumn">' + i18next.t("Background Color") + ':'
          + '</td>'
          + '<td  >'
          + '<p>'+ calendar.BackgroundColor +'</p>' 
          + '</td>'
          + '</tr>'
          + '</table>'
          + '</form>';
    var object = $('<div/>').html(frm_str).contents();

    return object;
  },
  getButtons: function () {
    buttons =  [];
    buttons.push({
      label: i18next.t("Cancel"),
      className: "btn btn-default pull-right"
    });
    if (window.CRM.calendarJSArgs.isModifiable) {
      buttons.push({
      label: i18next.t("Delete Calendar"),
      className: "btn btn-danger pull-left",
      callback: deleteCalendar
    });
    }
    return buttons;
  },
  show: function(calendar) {
    window.calendarPropertiesModal.calendar = calendar;
    var bootboxmessage = window.calendarPropertiesModal.getBootboxContent(calendar);
    window.calendarPropertiesModal.modal = bootbox.dialog({
      title: calendar.Name,
      message: bootboxmessage,
      show: true,
      buttons: window.calendarPropertiesModal.getButtons(),
      onEscape: function () {
        window.calendarPropertiesModal.modal.modal("hide");
      }
    });
    $("#NewAccessToken").click(window.calendarPropertiesModal.newAccessToken);
    $("#DeleteAccessToken").click(window.calendarPropertiesModal.deleteAccessToken);
    
  },
  newAccessToken: function() {
    window.CRM.APIRequest({
      method: 'POST',
      path: 'calendars/'+window.calendarPropertiesModal.calendar.Id+"/NewAccessToken",
    }).done(function (newcalendar) {
      $(window.calendarPropertiesModal.modal).find(".bootbox-body").html(window.calendarPropertiesModal.getBootboxContent(newcalendar));
      $("#NewAccessToken").click(window.calendarPropertiesModal.newAccessToken);
      $("#DeleteAccessToken").click(window.calendarPropertiesModal.deleteAccessToken);
    });
  },
  deleteAccessToken: function() {
    window.CRM.APIRequest({
      method: 'DELETE',
      path: 'calendars/'+window.calendarPropertiesModal.calendar.Id+"/AccessToken",
    }).done(function (newcalendar) {
      $(window.calendarPropertiesModal.modal).find(".bootbox-body").html(window.calendarPropertiesModal.getBootboxContent(newcalendar));
      $("#NewAccessToken").click(window.calendarPropertiesModal.newAccessToken);
    });
  }
}

function fieldError(inputField) {
  var p = $(inputField).parent().find("p .form-field-error")
  if (p.length === 0){
     p =  $("<p class='form-field-error'>");
     $(inputField).parent().append(p);
  }
  $(p).text(i18next.t("Invalid Entry"));
}

window.newCalendarModal = {
  getBootboxContent: function (){ 
    var frm_str = '<form id="some-form"><table class="table modal-table">'
          + '<tr>'
          + "<td>" + i18next.t('Calendar Name') + ":</td>"
          + '<td colspan="3">'
          + '<input id="calendarName" class="form-control" type="text"  />'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td>" + i18next.t('Foreground Color') + ":</td>"
          + '<td colspan="3">'
          + '<input id="ForegroundColor" class="form-control" type="text" placeholder="FFFFFF"  />'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td>" + i18next.t('Background Color') + ":</td>"
          + '<td colspan="3">'
          + '<input id="BackgroundColor" class="form-control" type="text" placeholder="000000" />'
          + '</td>'
          + '</tr>'
          + '</table>'
          + '</form>';
    var object = $('<div/>').html(frm_str).contents();

    return object;
  },
  getButtons: function () {
    buttons =  [];
    buttons.push({
      label: i18next.t("Save"),
      className: "btn btn-primary pull-right",
      callback: window.newCalendarModal.saveButtonCallback
    })
    buttons.push({
      label: i18next.t("Cancel"),
      className: "btn btn-default pull-left"
    });
   
    return buttons;
  },
  validateNewCalendar: function() {
    var status = true;
    if (!$("#calendarName").val()){
      fieldError($("#calendarName"));
      status = false;
    }
    if (!$("#ForegroundColor").val()){
      fieldError($("#ForegroundColor"));
      status = false;
    }
    if (!$("#BackgroundColor").val()){
      fieldError($("#BackgroundColor"));
      status = false;
    }
    return status;
  },
  saveButtonCallback: function() {
    $(".form-field-error").remove();
    if (!window.newCalendarModal.validateNewCalendar())
    {
      return false;
    }
    var newCalendar = {
      Name: $("#calendarName").val(),
      ForegroundColor: $("#ForegroundColor").val(),
      BackgroundColor: $("#BackgroundColor").val()
    }
    window.CRM.APIRequest({
      method:"POST",
      path: "calendars",
      data: JSON.stringify(newCalendar)
    }).done(function(data){
      initializeFilterSettings()
    });
  },
  show: function() {
    var bootboxmessage = window.newCalendarModal.getBootboxContent();
    window.calendarPropertiesModal.modal = bootbox.dialog({
      title: i18next.t("New Calendar"),
      message: bootboxmessage,
      show: true,
      buttons: window.newCalendarModal.getButtons(),
      onEscape: function () {
        window.calendarPropertiesModal.modal.modal("hide");
      }
    });
  }
}

function initializeCalendar() {
  //
  // initialize the calendar
  // -----------------------------------------------------------------
  window.CRM.fullcalendar =  $('#calendar').fullCalendar({
    header: {
      left: 'prev,next today',
      center: 'title',
      right: 'month,agendaWeek,agendaDay,listMonth'
    },
    height: 600,
    selectable: window.CRM.calendarJSArgs.isModifiable,
    editable: window.CRM.calendarJSArgs.isModifiable,
    eventStartEditable: window.CRM.calendarJSArgs.isModifiable,
    eventDurationEditable: window.CRM.calendarJSArgs.isModifiable,
    eventDrop: window.moveEventModal.handleEventDrop,
    eventResize: window.moveEventModal.handleEventResize,
    selectHelper: true,
    select: window.NewOrEditEventModal.getNewEventModal,
    eventClick: window.displayEventModal.getDisplayEventModal,
    locale: window.CRM.lang
  });
};

function getCalendarFilterElement(calendar,type,parent) {
  boxId = type+calendar.Id;
  return '<div class="panel box box-primary" style="border-top: 0px">'+
            '<div class="box-header" style="background-color:#'+calendar.BackgroundColor+'">' +
              '<h4 class="box-title">' +
                '<a data-toggle="collapse" data-parent="#'+parent+'" href="#'+boxId+'" aria-expanded="false" style="color:#'+calendar.ForegroundColor+'; font-size:10pt">' +
                  calendar.Name+
                '</a>'+
              '</h4>'+
            '</div>'+
           ' <div id="'+boxId+'" class="panel-collapse collapse" aria-expanded="false" style="">'+
              '<div class="box-body">'+
                "<div style='margin-bottom: 10px' class='checkbox'><input checked data-onstyle='info' data-width='100%' data-toggle='toggle' data-on='Show on Calendar' data-off='Hide on Calendar' type='checkbox' id='display-"+boxId+"' class='calendarSelectionBox' data-calendartype='"+type+"' data-calendarname='"+calendar.Name+"' data-calendarid='"+calendar.Id+"'/></div>" +
                "<div style='margin-bottom: 10px'><a class='btn btn-primary calendarfocus' style='width:100%' data-calendartype='"+type+"' data-calendarname='"+calendar.Name+"' data-calendarid='"+calendar.Id+"'>"+i18next.t('Focus')+"</a></div>"  +
                (type === "user"  ? '<div><a class="btn btn-warning calendarproperties" data-calendarid="'+calendar.Id+'" style="width:100%; white-space: unset; font-size:10pt">Properties</a></div>' : "") +
                '</div>'+
            '</div>'+
          '</div>';
}

function GetCalendarURL(calendarType, calendarID)
{
  var endpoint;
  if(calendarType=== "user") {
    endpoint="/api/calendars/"
  }
  else if(calendarType === "system")
  {
    endpoint="/api/systemcalendars/"
  }
  return window.CRM.root+endpoint+calendarID+"/fullcalendar";
}

function registerCalendarSelectionEvents() {
  
  
  $(document).on("change",".calendarSelectionBox", function(event) {
    if($(this).is(":checked")){
      var eventSourceURL = GetCalendarURL($(this).data('calendartype'),$(this).data("calendarid"));
      var alreadyPresent = window.CRM.fullcalendar.fullCalendar("getEventSources").find(function(element) {
        return element.url === eventSourceURL;
      })
      if (!alreadyPresent) {
        window.CRM.fullcalendar.fullCalendar("addEventSource",eventSourceURL);
      }
    }
    else {
      var eventSourceURL = GetCalendarURL($(this).data('calendartype'),$(this).data("calendarid"));
      window.CRM.fullcalendar.fullCalendar("removeEventSource", eventSourceURL);
    }
  });
  
  $(document).on("click",".calendarproperties", function(event) {
    window.CRM.APIRequest({
      method: 'GET',
      path: 'calendars/'+$(this).data("calendarid"),
    }).done(function (data) {
      var calendar = data.Calendars[0];
      window.calendarPropertiesModal.show(calendar);
    });
    
  });
  
  $(document).on("click",".calendarfocus", function(event) {
    var calendarTypeToKeep = $(this).data('calendartype');
    var calendarIDToKeep = $(this).data("calendarid");
    var calendarToKeepURL = GetCalendarURL(calendarTypeToKeep,calendarIDToKeep);
    $(".calendarSelectionBox").each(function(i,d) {
      if ($(d).data('calendartype')===calendarTypeToKeep && $(d).data('calendarid')===calendarIDToKeep) {
        $(d).prop('checked', true).change()
      }
      else{
        $(d).prop('checked', false).change()
      }
     });
     $(this).removeClass("calendarfocus");
     $(this).addClass("calendarunfocus");
     $(this).text(i18next.t("Unfocus"));
  });
  
  $(document).on("click",".calendarunfocus", function(event) {
  
    $(".calendarSelectionBox").each(function(i,d) {
        $(d).prop('checked', true).change()
     });
     $(this).removeClass("calendarunfocus");
     $(this).addClass("calendarfocus");
     $(this).text(i18next.t("Focus"));
  });
  
  $(document).on("click","#showAllUser", function(event) {
    showAllUserCalendars();
  });
  
}

function showAllUserCalendars() {
   window.CRM.APIRequest({
    method: 'GET',
    path: 'calendars',
  }).done(function (calendars) {
    $("#userCalendars").empty();
    $.each(calendars.Calendars,function(idx,calendar) {
      $("#userCalendars").append(getCalendarFilterElement(calendar,"user",'userCalendars'));

      window.CRM.fullcalendar.fullCalendar("addEventSource", GetCalendarURL("user",calendar.Id));
    });
    $(".calendarSelectionBox").filter('input[data-calendartype=user]').bootstrapToggle();
  });
}

function showAllSystemCalendars() {
  window.CRM.APIRequest({
    method: 'GET',
    path: 'systemcalendars',
  }).done(function (calendars) {
    $("#systemCalendars").empty();
    $.each(calendars.Calendars,function(idx,calendar) {
      $("#systemCalendars").append(getCalendarFilterElement(calendar,"system",'systemCalendars'))
      window.CRM.fullcalendar.fullCalendar("addEventSource", GetCalendarURL("system",calendar.Id));
    });
    $(".calendarSelectionBox").filter('input[data-calendartype=system]').bootstrapToggle();
  });
}

function initializeFilterSettings() {
  showAllUserCalendars();
  showAllSystemCalendars();
};

function initializeNewCalendarButton(){
  if (window.CRM.calendarJSArgs.isModifiable) {
    var newCalendarButton = '<div class="strike">'+
      '<span id="newCalendarButton"><i class="fa fa-plus-circle"></i></span>'+
    '</div>';
    
     $("#userCalendars").after(newCalendarButton);
  }
   $("#newCalendarButton").click(function() {
    window.newCalendarModal.show();
  });
 
}

function displayAccessTokenAPITest() {
  if (window.CRM.calendarJSArgs.countCalendarAccessTokens > 0 && !window.CRM.calendarJSArgs.bEnableExternalCalendarAPI){
    $(".content").prepend("<div class='callout callout-danger'><h4>"+i18next.t("Warning: bEnableExternalCalendarAPI disabled, but some calendars have access tokens")+"</h4><p>"+i18next.t("For calendars to be shared, the bEnableExternalCalendarAPI setting must be enabled in addition to the calendar having a specific access token")+"</p></div>");
  }
}

$(document).ready(function () {
  //window.CRM.calendarJSArgs.isModifiable = false;
  initializeCalendar();
  initializeFilterSettings();
  initializeNewCalendarButton();
  registerCalendarSelectionEvents();
  displayAccessTokenAPITest();
});
