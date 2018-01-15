
function BootboxContent() {
  var frm_str = '<form id="some-form">'
          + '<table class="table">'
          + '<tr>'
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Select your event type') + "</td>"
          + '<td colspan="3" class="TextColumn">'
          + '<select type="text" id="eventType" value="39"  width="100%" style="width: 100%">'
          //+"<option value='0' >" + i18next.t("Personal") + "</option>"
          + '</select>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Title') + ":</td>"
          + '<td colspan="1" class="TextColumn">'
          + "<input type='text' id='EventTitle' value='" + i18next.t("Calendar Title") + "' size='30' maxlength='100' class='form-control'  width='100%' style='width: 100%' required>"
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
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Group') + ":</td>"
          + '<td class="TextColumn">'
          + '<select type="text" id="EventGroup" value="39" width="100%" style="width: 100%">'
          + '</select>'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn'><span style='color: red'>*</span>" + i18next.t('Event Publicly Visible') + ":</td>"
          + '<td class="TextColumn">'
          + '<input type="checkbox" id="EventPubliclyVisible" />'
          + '</td>'
          + '</tr>'
          + '<tr>'
          + "<td class='LabelColumn' id='ATTENDENCES'>" + i18next.t('Attendance Counts') + "</td>"
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
          + '<td colspan="4" class="TextColumn">' + i18next.t('Event Sermon') + '<textarea name="EventText" rows="5" cols="80" class="form-control" id="eventPredication"  width="100%" style="width: 100%"></textarea></td>'
          + '</tr>'
          //+'<tr>'
          //+'<td class="LabelColumn"><span style="color: red">*</span>Statut de l&#39;événement:</td>'
          //+'<td colspan="3" class="TextColumn">'
          //+'<input type="radio" name="EventStatus" value="0" checked/> Actif      <input type="radio" name="EventStatus" value="1" /> Inactif    </td>'
          //+'</tr>'
          + '</table>'
          + '</form>';

  var object = $('<div/>').html(frm_str).contents();

  return object
}


function handleEventDrop(event, delta, revertFunc) {
  if (event.type == 'event') {
    bootbox.confirm({
      title: i18next.t("Move Event") + "?",
      message: i18next.t("Are you sure about this change?") + "\n" + event.title + " " + i18next.t("will be dropped."),
      buttons: {
        cancel: {
          label: '<i class="fa fa-times"></i> ' + i18next.t("Cancel")
        },
        confirm: {
          label: '<i class="fa fa-check"></i> ' + i18next.t("Confirm")
        }
      },
      callback: function (result) {
        if (result == true)// only event can be drag and drop, not anniversary or birthday
        {
          window.CRM.APIRequest({
            method: 'POST',
            path: 'events/',
            data: JSON.stringify({"evntAction": 'moveEvent', "eventID": event.eventID, "start": event.start.format()})
          }).done(function (data) {
            // now we can create the event
            $('#calendar').fullCalendar('removeEvents', event._id);// delete old one
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
}
;

function handleEventResize(event, delta, revertFunc) {
  if (event.type == 'event') {
    bootbox.confirm({
      title: i18next.t("Resize Event") + "?",
      message: i18next.t("Are you sure about this change?") + "\n" + event.title + " " + i18next.t("will be dropped."),
      buttons: {
        cancel: {
          label: '<i class="fa fa-times"></i> ' + i18next.t("Cancel")
        },
        confirm: {
          label: '<i class="fa fa-check"></i> ' + i18next.t("Confirm")
        }
      },
      callback: function (result) {
        if (result == true)// only event can be drag and drop, not anniversary or birthday
        {
          window.CRM.APIRequest({
            method: 'POST',
            path: 'events/',
            data: JSON.stringify({"evntAction": 'resizeEvent', "eventID": event.eventID, "end": event.end.format()})
          }).done(function (data) {
            // now we can create the event
            $('#calendar').fullCalendar('removeEvents', event._id);// delete old one
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
}
;

window.NewCalendarEventModal = {
  readDOMNewEvent: function() {
    var e = document.getElementById("eventType");
      var eventTypeID = e.options[e.selectedIndex].value;

      var EventTitle = $('form #EventTitle').val();
      var EventDesc = $('form #EventDesc').val();

      var e = document.getElementById("EventGroup");
      var EventGroupID = e.options[e.selectedIndex].value;
      var EventGroupType = e.options[e.selectedIndex].title;// we get the type of the group : personal or group for future dev
      var EventPubliclyVisible = $("form #EventPubliclyVisible").prop('checked');

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
          "evntAction": 'createEvent',
          "eventTypeID": eventTypeID,
          "EventGroupType": EventGroupType,
          "EventTitle": EventTitle,
          "EventDesc": EventDesc,
          "EventGroupID": EventGroupID,
          "EventPubliclyVisible": EventPubliclyVisible,
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
    var Event = window.NewCalendarEventModal.readDOMNewEvent();
    console.log(Event);
    return;
      window.CRM.APIRequest({
        method: 'POST',
        path: 'events/',
        data: JSON.stringify(Event)
      }).done(function (data) {
        $('#calendar').fullCalendar('renderEvent', data, true); // stick? = true             
        $('#calendar').fullCalendar('unselect');
        add = true;
        modal.modal("hide");

        var box = bootbox.dialog({message: i18next.t("Event was added successfully.")});

        setTimeout(function () {
          // be careful not to call box.hide() here, which will invoke jQuery's hide method
          box.modal('hide');
        }, 1000);
        return true;
      });

      return add;
  },  
  getSaveButton: function() {
    return {
      label: i18next.t("Save"),
      className: "btn btn-primary pull-left",
      callback: window.NewCalendarEventModal.saveButtonCallback
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
      path: 'groups/calendars',
    }).done(function (groups) {
      var elt = document.getElementById("EventGroup");
      var len = groups.length;

      // We add the none option
      var option = document.createElement("option");
      option.text = i18next.t("None");
      option.value = 0;
      option.title = "";
      elt.appendChild(option);

      for (i = 0; i < len; ++i) {
        var option = document.createElement("option");
        // there is a groups.type in function of the new plan of schema
        option.text = groups[i].name;
        option.title = groups[i].type;
        option.value = groups[i].groupID;
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
    window.NewCalendarEventModal.loadCalendars();
    window.NewCalendarEventModal.loadEventTypes();
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

    // this will ensure that image and table can be focused
    $(document).on('focusin', function (e) {
      e.stopImmediatePropagation();
    });

    $('#EventTitle').on('click', function () {
      if (this.defaultValue == i18next.t("Calendar Title")) {
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
  getNewEventModal: function(start,end) {
    window.NewCalendarEventModal.modal = bootbox.dialog({
      message: BootboxContent(),
      title: i18next.t("Event Creation"),
      buttons: [
        window.NewCalendarEventModal.getSaveButton(),
        window.NewCalendarEventModal.getCloseButton()
      ],
      show: false,
      onEscape: function () {
        window.NewCalendarEventModal.modal.modal("hide");
      }
    });
    window.NewCalendarEventModal.modal.modal("show");
    window.NewCalendarEventModal.configureModalUIElements(start,end);
  }
};

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
    selectable: calendarJSArgs.isModifiable,
    editable: calendarJSArgs.isModifiable,
    eventDrop: handleEventDrop,
    eventResize: handleEventResize,
    selectHelper: true,
    select: window.NewCalendarEventModal.getNewEventModal,
    locale: window.CRM.lang
  });
};


function getCalendarFilterElement(calendar,type) {
  return "<div>" + 
         "<input type='checkbox' class='calendarSelectionBox' data-calendartype='"+type+"' data-calendarname='"+calendar.Name+"' data-calendarid='"+calendar.Id+"'/>"+ 
         "<label for='"+calendar.Name+"'>"+calendar.Name+"</label>"+
         "<div style='display:inline-block; padding-left:5px; padding-right:5px; float:right; color:#"+calendar.ForegroundColor+"; background-color:#"+calendar.BackgroundColor+";'><i class='fa fa-calendar' aria-hidden='true'></i></div>" +
         "</div>";
}

function registerCalendarSelectionEvents() {
  $(document).on
  
  $(document).on("click",".calendarSelectionBox", function(event) {
    console.log("box checked");
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
      window.CRM.fullcalendar.fullCalendar("addEventSource",eventSourceURL);
    }
    else {
      var eventSourceURL = window.CRM.root+endpoint+$(this).data("calendarid")+"/fullcalendar";
      window.CRM.fullcalendar.fullCalendar("removeEventSource",eventSourceURL);
    }
  })
}

function initializeFilterSettings() {
  
  window.CRM.APIRequest({
    method: 'GET',
    path: 'calendars',
  }).done(function (calendars) {
    window.asdf=calendars;
    $.each(calendars.Calendars,function(idx,calendar) {
      console.log(calendar);
      $("#userCalendars").append(getCalendarFilterElement(calendar,"user"))
    });
    $("#userCalendars .calendarSelectionBox").click();
  });
 
  window.CRM.APIRequest({
    method: 'GET',
    path: 'systemcalendars',
  }).done(function (calendars) {
    window.asdf=calendars;
    $.each(calendars.Calendars,function(idx,calendar) {
      console.log(calendar);
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
