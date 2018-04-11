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


window.getDisplayEventModal = function(event) {
   window.showEventForm(event.id, window.CRM.refreshAllFullCalendarSources);
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
    //select: window.NewOrEditEventModal.getNewEventModal,
    eventClick: window.getDisplayEventModal,
    locale: window.CRM.lang
  });
};

function getCalendarFilterElement(calendar,type) {
  return "<div class='row calendar-filter-row'>" + 
          "<div class='col-xs-1'>" +
            "<input type='checkbox' checked class='calendarSelectionBox' data-calendartype='"+type+"' data-calendarname='"+calendar.Name+"' data-calendarid='"+calendar.Id+"'/>"+ 
          "</div>"+
          "<div class='col-xs-7'><label for='"+calendar.Name+"'>"+calendar.Name+"</label></div>"+
          "<div class='col-xs-4'>"+
            "<div class='calendar-filter-text' style=' color:#"+calendar.ForegroundColor+"; background-color:#"+calendar.BackgroundColor+";'><i class='fa fa-calendar' aria-hidden='true'></i></div>"+
            (type === "user"  ? "<div class='calendar-filter-text'><i class='calendarproperties fa fa-eye' aria-hidden='true' data-calendarid='"+calendar.Id+"' ></i></div>" :"") +
        "</div>";
}


function getFullCalendarSource (caltype,calendarid)
{
  if(caltype === "user") {
      endpoint="/api/calendars/"
    }
    else if(caltype === "system")
    {
      endpoint="/api/systemcalendars/"
    }
    eventSourceURL = window.CRM.root +  endpoint + calendarid +"/fullcalendar"
    return {id: caltype+calendarid, url: eventSourceURL}
}

function registerCalendarSelectionEvents() {
  
  $(document).on("click",".calendarSelectionBox", function(event) {
    console.log("Clicked");
    var endpoint;
    var caltype = $(this).data('calendartype');
    var fullCalObject = getFullCalendarSource(caltype, $(this).data("calendarid"));
    if($(this).is(":checked")){
      window.CRM.fullcalendar.fullCalendar("addEventSource",fullCalObject);
    }
    else {
      window.CRM.fullcalendar.fullCalendar("removeEventSource",fullCalObject);
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
      $("#userCalendars").append(getCalendarFilterElement(calendar,"user"));
      var fullCalObject = getFullCalendarSource("user", calendar.Id);
      window.CRM.fullcalendar.fullCalendar("addEventSource",fullCalObject);
    });
    
  });
}

function showAllSystemCalendars() {
  window.CRM.APIRequest({
    method: 'GET',
    path: 'systemcalendars',
  }).done(function (calendars) {
    $("#systemCalendars").empty();
    $.each(calendars.Calendars,function(idx,calendar) {
      $("#systemCalendars").append(getCalendarFilterElement(calendar,"system"));
       var fullCalObject = getFullCalendarSource("system", calendar.Id);
      window.CRM.fullcalendar.fullCalendar("addEventSource",fullCalObject);
    });
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
