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
          + '<table class="table">'
          + '<tr>'
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Type') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<select type="text" id="eventType" value="39"  width="100%" style="width: 100%">'
          + '</select>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Desc') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + "<textarea id='EventDesc' rows='4' maxlength='100' class='form-control'  width='100%' style='width: 100%' required >" + i18next.t("Calendar description") + "</textarea>"
          + '</td>'
          + '</tr>'
          + '<tr>'
          + '<td class="LabelColumn"><span style="color: red">*</span>'
          + i18next.t("Date Range") + ':'
          + '</td>'
          + '<td class="TextColumn">'
          + '<input type="text" name="EventDateRange" value="" maxlength="10" id="EventDateRange" size="50" class="form-control" width="100%" style="width: 100%" required>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Pinned Calendars') + ":</td>"
          + '<td class="TextColumn">'
          + '<select type="text" multiple="multiple" id="PinnedCalendars" value="39" width="100%" style="width: 100%">'
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
          + '<input type="text" id="Total" value="0" size="8" class="form-control"  width="100%" style="width: 100%">'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td><strong>" + i18next.t("Members") + ":&nbsp;</strong></td>"
          + '<td>'
          + '<input type="text" id="Members" value="0" size="8" class="form-control"  width="100%" style="width: 100%">'
          + ' </td>'
          + '</tr>'
          + ' <tr>'
          + "<td><strong>" + i18next.t("Visitors") + ":&nbsp;</strong></td>"
          + '<td>'
          + '<input type="text" id="Visitors" value="0" size="8" class="form-control"  width="100%" style="width: 100%">'
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
          + '<td colspan="4" class="TextColumn">' + i18next.t('Event Description') + '<textarea name="EventText" rows="5" cols="80" class="form-control" id="eventPredication"  width="100%" style="width: 100%"></textarea></td>'
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
      var start = moment(dateRange[0]).format();
      var end = moment(dateRange[1]).format();
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
      className: "btn btn-primary pull-left",
      callback: window.NewOrEditEventModal.saveButtonCallback
    };
  },
  getCloseButton: function() {
    return {
      label: i18next.t("Close"),
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
      timePickerIncrement: 30,
      linkedCalendars: true,
      showDropdowns: true,
      locale: {
        format: 'YYYY-MM-DD h:mm A'
      },
      minDate: 1 / 1 / 1900,
      startDate: start.format("YYYY-MM-DD h:mm A"),
      endDate: end.format("YYYY-MM-DD h:mm A")
    });
    
    $("#PinnedCalendars").select2();

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
      title: "<input type='text' id='EventTitle' value='" + i18next.t("Event Title") + "' size='30' maxlength='100' class='form-control'  width='100%' style='width: 100%' required>",
      buttons: [
        window.NewOrEditEventModal.getSaveButton(),
        window.NewOrEditEventModal.getCloseButton()
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
        title: "<input type='text' id='EventTitle' value='" + event.Title + "' size='30' maxlength='100' class='form-control'  width='100%' style='width: 100%' required>",
        buttons: [
          window.NewOrEditEventModal.getSaveButton(),
          window.NewOrEditEventModal.getCloseButton()
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
    var frm_str = '<table class="table">'
          + '<tr>'
          + "<td><span style='color: red'>*</span>" + i18next.t('Event Type') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<p>' + event.Type + "</p>" 
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Desc') + ":</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<p>' + event.Desc + "</p>" 
          + '</td>'
          + '</tr>'
          + '<tr>'
          + '<td class="LabelColumn"><span style="color: red">*</span>'
          + i18next.t("Date Range") + ':'
          + '</td>'
          + '<td class="TextColumn">'
          + '<p>'+new moment(event.Start).format()+ " - " + new moment(event.End).format() +'</p>' 
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Pinned Calendars') + ":</td>"
          + '<td class="TextColumn">'
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
      label: i18next.t("Close"),
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

window.calendarPropertiesModal = {
  getBootboxContent: function (calendar){ 
    var icsURL = '';
    var jsonURL = '';
    if (calendar.AccessToken){
      icsURL =  window.CRM.fullURL + "api/public/calendar/"+calendar.AccessToken+"/ics";
      jsonURL =  window.CRM.fullURL + "api/public/calendar/"+calendar.AccessToken+"/events";
    }
    var frm_str = '<form id="some-form"><table class="table" style="table-layout:fixed;width:100%">'
          + '<tr>'
          + "<td>" + i18next.t('Access Token') + ":</td>"
          + '<td colspan="3">'
          + '<input id="AccessToken" class="form-control" type="text" readonly value="' + calendar.AccessToken + '"/>'
          + '<a id="NewAccessToken" class="btn btn-warning"><i class="fa fa-repeat"></i>' + i18next.t("New Access Token") + '</a>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'>" + i18next.t('ICS URL') + ":</td>"
          + '<td colspan="3" style="word-wrap: break-word;">'
          + '<span ><a href="'+icsURL+'">'+icsURL+'</a></span>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'>" + i18next.t('JSON URL') + ":</td>"
          + '<td colspan="3" style="word-wrap: break-word;">'
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
      label: i18next.t("Close"),
      className: "btn btn-default pull-right"
    });
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
  },
  newAccessToken: function() {
    window.CRM.APIRequest({
      method: 'POST',
      path: 'calendars/'+window.calendarPropertiesModal.calendar.Id+"/NewAccessToken",
    }).done(function (newcalendar) {
      $(window.calendarPropertiesModal.modal).find(".bootbox-body").html(window.calendarPropertiesModal.getBootboxContent(newcalendar));
      $("#NewAccessToken").click(window.calendarPropertiesModal.newAccessToken);
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
    eventDrop: window.moveEventModal.handleEventDrop,
    eventResize: window.moveEventModal.handleEventResize,
    selectHelper: true,
    select: window.NewOrEditEventModal.getNewEventModal,
    eventClick: window.displayEventModal.getDisplayEventModal,
    locale: window.CRM.lang
  });
};

function getCalendarFilterElement(calendar,type) {
  return "<div>" + 
         "<input type='checkbox' class='calendarSelectionBox' data-calendartype='"+type+"' data-calendarname='"+calendar.Name+"' data-calendarid='"+calendar.Id+"'/>"+ 
         "<label for='"+calendar.Name+"'>"+calendar.Name+"</label>"+
         "<div style='float:right;'>"+
         "<div style='display:inline-block; padding-left:5px; padding-right:5px; color:#"+calendar.ForegroundColor+"; background-color:#"+calendar.BackgroundColor+";'><i class='fa fa-calendar' aria-hidden='true'></i></div>"+
         (type === "user"  ? "<div style='display:inline-block; padding-left:5px; padding-right:5px;'><i class='calendarproperties fa fa-eye' aria-hidden='true' data-calendarid='"+calendar.Id+"' ></i></div>" :"") +
         "</div>";
}

function registerCalendarSelectionEvents() {
  
  $(document).on("click",".calendarSelectionBox", function(event) {
    var endpoint;
    if($(this).data('calendartype') === "user") {
      endpoint="/api/calendars/"
    }
    else if($(this).data('calendartype') === "system")
    {
      endpoint="/api/systemcalendars/"
    }
    if($(this).is(":checked")){
      var eventSourceURL = window.CRM.root+endpoint+$(this).data("calendarid")+"/fullcalendar";
      window.CRM.fullcalendar.fullCalendar("addEventSource",{id: $(this).data("calendarid"), url: eventSourceURL});
    }
    else {
      var eventSourceURL = window.CRM.root+endpoint+$(this).data("calendarid")+"/fullcalendar";
      window.CRM.fullcalendar.fullCalendar("removeEventSource",{id: $(this).data("calendarid"), url: eventSourceURL});
    }
  });
  
  $(document).on("click",".calendarproperties", function(event) {
    window.CRM.APIRequest({
      method: 'GET',
      path: 'calendars/'+$(this).data("calendarid"),
    }).done(function (data) {
      var calendar = data.Calendars[0];
      console.log(calendar);
      window.calendarPropertiesModal.show(calendar);
    });
    
  });
}

function initializeFilterSettings() {
  
  window.CRM.APIRequest({
    method: 'GET',
    path: 'calendars',
  }).done(function (calendars) {
    $.each(calendars.Calendars,function(idx,calendar) {
      $("#userCalendars").append(getCalendarFilterElement(calendar,"user"))
    });
    $("#userCalendars .calendarSelectionBox").click();
  });
 
  window.CRM.APIRequest({
    method: 'GET',
    path: 'systemcalendars',
  }).done(function (calendars) {
    $.each(calendars.Calendars,function(idx,calendar) {
      $("#systemCalendars").append(getCalendarFilterElement(calendar,"system"))
    });
    $("#systemCalendars .calendarSelectionBox").click();
  });
  
  registerCalendarSelectionEvents();
  
};

$(document).ready(function () {
  initializeCalendar();
  initializeFilterSettings();
});
