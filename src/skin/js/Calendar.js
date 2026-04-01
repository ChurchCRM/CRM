window.moveEventModal = {
  getButtons: function () {
    return {
      cancel: {
        label: '<i class="fa-solid fa-times"></i> ' + i18next.t("Cancel"),
      },
      confirm: {
        label: '<i class="fa-solid fa-check"></i> ' + i18next.t("Confirm"),
      },
    };
  },
  modalCallBack: function (result) {
    if (result === true) {
      window.CRM.APIRequest({
        method: "POST",
        path: "events/" + window.moveEventModal.event.id + "/time",
        data: JSON.stringify({
          startTime: window.moveEventModal.event.start.toISOString(),
          endTime: window.moveEventModal.event.end.toISOString(),
        }),
      });
    } else {
      window.moveEventModal.revertFunc();
    }
  },
  handleEventDrop: function (info) {
    var event = info.event;
    var revertFunc = info.revert;
    var originalStart = info.oldEvent.start.toLocaleString();
    var newStart = event.start.toLocaleString();
    window.moveEventModal.revertFunc = revertFunc;
    window.moveEventModal.event = event;
    bootbox.confirm({
      title: i18next.t("Move Event") + "?",
      message:
        i18next.t("Are you sure you want to move") +
        " " +
        event.title +
        " " +
        i18next.t("from") +
        "<br/>" +
        originalStart +
        "<br/>" +
        i18next.t("to") +
        "<br/>" +
        newStart,
      buttons: window.moveEventModal.getButtons(),
      callback: window.moveEventModal.modalCallBack,
    });
  },
  handleEventResize: function (info) {
    var event = info.event;
    var revertFunc = info.revert;
    var originalEnd = info.oldEvent.end.toLocaleString();
    var newEnd = event.end.toLocaleString();
    window.moveEventModal.revertFunc = revertFunc;
    window.moveEventModal.event = event;
    bootbox.confirm({
      title: i18next.t("Resize Event") + "?",
      message:
        i18next.t("Are you sure you want to change the end time for ") +
        " " +
        event.title +
        " " +
        i18next.t("from") +
        "<br/>" +
        originalEnd +
        "<br/>" +
        i18next.t("to") +
        "<br/>" +
        newEnd,
      buttons: window.moveEventModal.getButtons(),
      callback: window.moveEventModal.modalCallBack,
    });
  },
};

window.CRM.refreshAllFullCalendarSources = function () {
  window.CRM.fullcalendar.refetchEvents();
};

function deleteCalendar() {
  window.CRM.APIRequest({
    method: "DELETE",
    path: "calendars/" + window.calendarPropertiesModal.calendar.Id,
  }).done(function () {
    var eventSource = window.CRM.fullcalendar.getEventSourceById("user-" + window.calendarPropertiesModal.calendar.Id);
    if (eventSource) {
      eventSource.remove();
    }
    initializeFilterSettings();
    setTimeout(function () {
      window.location.reload();
    }, 1000);
  });
}

window.calendarPropertiesModal = {
  getBootboxContent: function (calendar) {
    var HTMLURL = "";
    var icsURL = "";
    var jsonURL = "";
    if (calendar.AccessToken) {
      HTMLURL = window.CRM.fullURL + "/external/calendars/" + calendar.AccessToken;
      icsURL = window.CRM.fullURL + "/api/public/calendar/" + calendar.AccessToken + "/ics";
      jsonURL = window.CRM.fullURL + "/api/public/calendar/" + calendar.AccessToken + "/events";
    }
    var frm_str =
      '<form id="some-form"><table class="table modal-table">' +
      "<tr>" +
      "<td>" +
      i18next.t("Access Token") +
      ":</td>" +
      '<td colspan="3">' +
      '<input id="AccessToken" class="form-control" type="text" readonly value="' +
      calendar.AccessToken +
      '"/>' +
      (window.CRM.calendarJSArgs.isModifiable
        ? '<a id="NewAccessToken" class="btn btn-warning"><i class="fa-solid fa-repeat me-1"></i>' +
          i18next.t("New Access Token") +
          "</a>"
        : "") +
      (window.CRM.calendarJSArgs.isModifiable && calendar.AccessToken != null
        ? '<a id="DeleteAccessToken" class="btn btn-danger"><i class="fa-solid fa-trash-can me-1"></i>' +
          i18next.t("Delete Access Token") +
          "</a>"
        : "") +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td class='LabelColumn'>" +
      i18next.t("HTML URL") +
      ":</td>" +
      '<td colspan="3">' +
      '<span ><a href="' +
      HTMLURL +
      '">' +
      HTMLURL +
      "</a></span>" +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td class='LabelColumn'>" +
      i18next.t("ICS URL") +
      ":</td>" +
      '<td colspan="3">' +
      '<span ><a href="' +
      icsURL +
      '">' +
      icsURL +
      "</a></span>" +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td class='LabelColumn'>" +
      "JSON URL" +
      ":</td>" +
      '<td colspan="3">' +
      '<span ><a href="' +
      jsonURL +
      '">' +
      jsonURL +
      "</a></span>" +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td class='LabelColumn'>" +
      i18next.t("Foreground Color") +
      ":</td>" +
      "<td >" +
      "<p>" +
      calendar.ForegroundColor +
      "</p>" +
      "</td>" +
      "</tr>" +
      "<tr>" +
      '<td class="LabelColumn">' +
      i18next.t("Background Color") +
      ":" +
      "</td>" +
      "<td  >" +
      "<p>" +
      calendar.BackgroundColor +
      "</p>" +
      "</td>" +
      "</tr>" +
      "</table>" +
      "</form>";
    var object = $("<div/>").html(frm_str).contents();

    return object;
  },
  getButtons: function () {
    var buttons = [];
    buttons.push({
      label: i18next.t("Cancel"),
      className: "btn btn-default float-end",
    });
    if (window.CRM.calendarJSArgs.isModifiable) {
      buttons.push({
        label: i18next.t("Delete Calendar"),
        className: "btn btn-danger float-start",
        callback: deleteCalendar,
      });
    }
    return buttons;
  },
  show: function (calendar) {
    window.calendarPropertiesModal.calendar = calendar;
    var bootboxmessage = window.calendarPropertiesModal.getBootboxContent(calendar);
    window.calendarPropertiesModal.modal = bootbox.dialog({
      title: calendar.Name,
      message: bootboxmessage,
      show: true,
      buttons: window.calendarPropertiesModal.getButtons(),
      onEscape: function () {
        window.calendarPropertiesModal.modal.modal("hide");
      },
    });
    $("#NewAccessToken").click(window.calendarPropertiesModal.newAccessToken);
    $("#DeleteAccessToken").click(window.calendarPropertiesModal.deleteAccessToken);
  },
  newAccessToken: function () {
    window.CRM.APIRequest({
      method: "POST",
      path: "calendars/" + window.calendarPropertiesModal.calendar.Id + "/NewAccessToken",
    }).done(function (newcalendar) {
      var closeBtn = $(
        '<button class="btn btn-primary" style="margin-top:15px;float:right;">' + i18next.t("Close") + "</button>",
      );
      var $body = $(window.calendarPropertiesModal.modal).find(".bootbox-body");
      $body.html(window.calendarPropertiesModal.getBootboxContent(newcalendar));
      $body.append(closeBtn);
      $("#NewAccessToken").click(window.calendarPropertiesModal.newAccessToken);
      $("#DeleteAccessToken").click(window.calendarPropertiesModal.deleteAccessToken);
      closeBtn.on("click", function () {
        window.calendarPropertiesModal.modal.modal("hide");
      });

      var calendarRow = $('[data-calendarid="' + newcalendar.Id + '"]').closest("tr");
      if (calendarRow.length > 0) {
        var accessTokenCell = calendarRow.find(".calendar-access-token-cell");
        if (accessTokenCell.length > 0) {
          accessTokenCell.text(newcalendar.AccessToken);
        }
      }

      bootbox.alert(i18next.t("A Calendar access token has been generated and saved."));
      window.calendarPropertiesModal.calendar = newcalendar;
    });
  },
  deleteAccessToken: function () {
    window.CRM.APIRequest({
      method: "DELETE",
      path: "calendars/" + window.calendarPropertiesModal.calendar.Id + "/AccessToken",
    }).done(function (newcalendar) {
      var calendarRow = $('[data-calendarid="' + newcalendar.Id + '"]').closest("tr");
      if (calendarRow.length > 0) {
        var accessTokenCell = calendarRow.find(".calendar-access-token-cell");
        if (accessTokenCell.length > 0) {
          accessTokenCell.text("");
        } else {
          if (!window.CRM.calendarJSArgs.isModifiable || !newcalendar.AccessToken) {
            calendarRow.remove();
          }
        }
      }

      if (window.calendarPropertiesModal.modal) {
        window.calendarPropertiesModal.modal.modal("hide");
      }

      setTimeout(function () {
        document.body.focus();
      }, 300);
    });
  },
};

function fieldError(inputField) {
  var p = $(inputField).parent().find("p .form-field-error");
  if (p.length === 0) {
    p = $("<p class='form-field-error'>");
    $(inputField).parent().append(p);
  }
  $(p).text(i18next.t("Invalid Entry"));
}

window.newCalendarModal = {
  getBootboxContent: function () {
    var frm_str =
      '<form id="some-form"><table class="table modal-table">' +
      "<tr>" +
      "<td>" +
      i18next.t("Calendar Name") +
      ":</td>" +
      '<td colspan="3">' +
      '<input id="calendarName" class="form-control" type="text"  />' +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td>" +
      i18next.t("Foreground Color") +
      ":</td>" +
      '<td colspan="3">' +
      '<input id="ForegroundColor" class="form-control" type="text" placeholder="FFFFFF"  />' +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td>" +
      i18next.t("Background Color") +
      ":</td>" +
      '<td colspan="3">' +
      '<input id="BackgroundColor" class="form-control" type="text" placeholder="000000" />' +
      "</td>" +
      "</tr>" +
      "</table>" +
      "</form>";
    var object = $("<div/>").html(frm_str).contents();

    return object;
  },
  getButtons: function () {
    var buttons = [];
    buttons.push({
      label: i18next.t("Save"),
      className: "btn btn-primary float-end",
      callback: window.newCalendarModal.saveButtonCallback,
    });
    buttons.push({
      label: i18next.t("Cancel"),
      className: "btn btn-default float-start",
    });

    return buttons;
  },
  validateNewCalendar: function () {
    var status = true;
    if (!$("#calendarName").val()) {
      fieldError($("#calendarName"));
      status = false;
    }
    if (!$("#ForegroundColor").val()) {
      fieldError($("#ForegroundColor"));
      status = false;
    }
    if (!$("#BackgroundColor").val()) {
      fieldError($("#BackgroundColor"));
      status = false;
    }
    return status;
  },
  saveButtonCallback: function () {
    $(".form-field-error").remove();
    if (!window.newCalendarModal.validateNewCalendar()) {
      return false;
    }
    var newCalendar = {
      Name: $("#calendarName").val(),
      ForegroundColor: $("#ForegroundColor").val(),
      BackgroundColor: $("#BackgroundColor").val(),
    };
    window.CRM.APIRequest({
      method: "POST",
      path: "calendars",
      data: JSON.stringify(newCalendar),
    }).done(function () {
      initializeFilterSettings();
    });
  },
  show: function () {
    var bootboxmessage = window.newCalendarModal.getBootboxContent();
    window.calendarPropertiesModal.modal = bootbox.dialog({
      title: i18next.t("New Calendar"),
      message: bootboxmessage,
      show: true,
      buttons: window.newCalendarModal.getButtons(),
      onEscape: function () {
        window.calendarPropertiesModal.modal.modal("hide");
      },
    });
  },
};

function initializeCalendar() {
  window.CRM.isCalendarLoading = false;

  if (window.CRM.fullcalendar) {
    window.CRM.fullcalendar.destroy();
  }

  var mobileHeaderToolbar = {
    start: "prev,next",
    center: "title",
    end: "today",
  };
  var desktopHeaderToolbar = {
    start: "prev,next today",
    center: "title",
    end: "dayGridMonth,timeGridWeek,timeGridDay,listMonth",
  };
  var mobileFooterToolbar = { end: "dayGridMonth,timeGridWeek,timeGridDay,listMonth" };

  window.CRM.fullcalendar = new FullCalendar.Calendar(document.getElementById("calendar"), {
    locale: window.CRM.lang || "en",
    timeZone: window.CRM.calendarJSArgs.sTimeZone || "local",
    headerToolbar: window.innerWidth < 768 ? mobileHeaderToolbar : desktopHeaderToolbar,
    footerToolbar: window.innerWidth < 768 ? mobileFooterToolbar : false,
    contentHeight: "auto",
    windowResizeDelay: 200,
    windowResize: function () {
      var nowMobile = window.innerWidth < 768;
      window.CRM.fullcalendar.setOption("headerToolbar", nowMobile ? mobileHeaderToolbar : desktopHeaderToolbar);
      window.CRM.fullcalendar.setOption("footerToolbar", nowMobile ? mobileFooterToolbar : false);
    },
    selectable: true,
    editable: window.CRM.calendarJSArgs.isModifiable,
    eventDrop: window.moveEventModal.handleEventDrop,
    eventResize: window.moveEventModal.handleEventResize,
    selectMirror: true,
    select: window.showNewEventForm,
    eventClick: function (info) {
      var eventData = info.event;
      var jsEvent = info.jsEvent;
      jsEvent.preventDefault();

      var eventSourceParams = eventData.source.url.split("/");
      var eventSourceType = eventSourceParams[eventSourceParams.length - 3];
      if (eventData.url !== "null") {
        window.open(eventData.url);
      } else if (eventData.editable || eventData.startEditable || eventData.durationEditable) {
        window.showEventForm(eventData);
      } else {
        alert(i18next.t("Holiday") + ": " + eventData.title);
      }
    },
    loading: function (isLoading) {
      window.CRM.isCalendarLoading = isLoading;
    },
  });
}

/**
 * Build a stable event source URL using a deterministic source ID.
 * No cache-busting parameter — FullCalendar refetches via refetchEvents().
 */
function GetCalendarURL(calendarType, calendarID) {
  var endpoint;
  if (calendarType === "user") {
    endpoint = "/api/calendars/";
  } else if (calendarType === "system") {
    endpoint = "/api/systemcalendars/";
  }
  return window.CRM.root + endpoint + calendarID + "/fullcalendar";
}

/**
 * Get the deterministic event source ID for a calendar.
 */
function GetCalendarSourceId(calendarType, calendarID) {
  return calendarType + "-" + calendarID;
}

/**
 * Build a sidebar list-group item for a calendar with a BS5 form-switch toggle.
 */
function getCalendarFilterElement(calendar, type) {
  var sourceId = GetCalendarSourceId(type, calendar.Id);
  var switchId = "display-" + sourceId;

  var html =
    '<div class="list-group-item px-2 py-2" data-source-id="' +
    sourceId +
    '">' +
    '<div class="d-flex align-items-center">' +
    '<span class="avatar avatar-sm rounded me-2" style="background-color:#' +
    calendar.BackgroundColor +
    "; color:#" +
    calendar.ForegroundColor +
    '"><i class="fa-solid fa-calendar"></i></span>' +
    '<div class="flex-fill">' +
    '<div class="fw-medium small">' +
    calendar.Name +
    "</div>" +
    "</div>" +
    '<div class="form-check form-switch ms-2 mb-0">' +
    '<input type="checkbox" class="form-check-input calendarSelectionBox" id="' +
    switchId +
    '" checked data-calendartype="' +
    type +
    '" data-calendarid="' +
    calendar.Id +
    '"/>' +
    "</div>" +
    "</div>" +
    (type === "user"
      ? '<div class="mt-1 d-flex gap-1">' +
        '<button class="btn btn-sm btn-ghost-primary calendarfocus flex-fill" data-calendartype="' +
        type +
        '" data-calendarid="' +
        calendar.Id +
        '"><i class="fa-solid fa-crosshairs me-1"></i>' +
        i18next.t("Focus") +
        "</button>" +
        '<button class="btn btn-sm btn-ghost-secondary calendarproperties" data-calendarid="' +
        calendar.Id +
        '"><i class="fa-solid fa-gear"></i></button>' +
        "</div>"
      : "") +
    "</div>";

  return html;
}

/**
 * Remove all existing FullCalendar event sources to prevent duplicates.
 */
function clearAllEventSources() {
  var sources = window.CRM.fullcalendar.getEventSources();
  for (var i = sources.length - 1; i >= 0; i--) {
    sources[i].remove();
  }
}

/**
 * Add an event source with a deterministic ID (prevents duplicates).
 */
function addCalendarEventSource(calendarType, calendarID) {
  var sourceId = GetCalendarSourceId(calendarType, calendarID);
  var existing = window.CRM.fullcalendar.getEventSourceById(sourceId);
  if (!existing) {
    window.CRM.fullcalendar.addEventSource({
      id: sourceId,
      url: GetCalendarURL(calendarType, calendarID),
    });
  }
}

/**
 * Remove an event source by its deterministic ID.
 */
function removeCalendarEventSource(calendarType, calendarID) {
  var sourceId = GetCalendarSourceId(calendarType, calendarID);
  var existing = window.CRM.fullcalendar.getEventSourceById(sourceId);
  if (existing) {
    existing.remove();
  }
}

function registerCalendarSelectionEvents() {
  $(document).on("change", ".calendarSelectionBox", function () {
    var calendarType = $(this).data("calendartype");
    var calendarId = $(this).data("calendarid");

    if ($(this).is(":checked")) {
      addCalendarEventSource(calendarType, calendarId);
    } else {
      removeCalendarEventSource(calendarType, calendarId);
    }
  });

  $(document).on("click", ".calendarproperties", function () {
    window.CRM.APIRequest({
      method: "GET",
      path: "calendars/" + $(this).data("calendarid"),
    }).done(function (data) {
      var calendar = data.Calendars && data.Calendars[0] ? data.Calendars[0] : null;
      if (calendar && typeof calendar.AccessToken !== "undefined") {
        window.calendarPropertiesModal.show(calendar);
      } else {
        bootbox.alert(
          i18next.t(
            "Calendar properties could not be loaded. This calendar may be missing an access token or is not configured correctly.",
          ),
        );
      }
    });
  });

  $(document).on("click", ".calendarfocus", function () {
    var calendarTypeToKeep = $(this).data("calendartype");
    var calendarIDToKeep = $(this).data("calendarid");
    $(".calendarSelectionBox").each(function () {
      if (
        $(this).data("calendartype") === calendarTypeToKeep &&
        $(this).data("calendarid") === calendarIDToKeep
      ) {
        if (!$(this).is(":checked")) {
          $(this).prop("checked", true).trigger("change");
        }
      } else {
        if ($(this).is(":checked")) {
          $(this).prop("checked", false).trigger("change");
        }
      }
    });
    $(this).removeClass("calendarfocus").addClass("calendarunfocus");
    $(this).html('<i class="fa-solid fa-expand me-1"></i>' + i18next.t("Unfocus"));
  });

  $(document).on("click", ".calendarunfocus", function () {
    $(".calendarSelectionBox").each(function () {
      if (!$(this).is(":checked")) {
        $(this).prop("checked", true).trigger("change");
      }
    });
    $(this).removeClass("calendarunfocus").addClass("calendarfocus");
    $(this).html('<i class="fa-solid fa-crosshairs me-1"></i>' + i18next.t("Focus"));
  });
}

function showAllUserCalendars() {
  window.CRM.APIRequest({
    method: "GET",
    path: "calendars",
    suppressErrorDialog: true,
  }).done(function (calendars) {
    $("#calendarUserList").empty();
    $.each(calendars.Calendars, function (idx, calendar) {
      $("#calendarUserList").append(getCalendarFilterElement(calendar, "user"));
      addCalendarEventSource("user", calendar.Id);
    });
  });
}

function showAllSystemCalendars() {
  window.CRM.APIRequest({
    method: "GET",
    path: "systemcalendars",
    suppressErrorDialog: true,
  }).done(function (calendars) {
    $("#calendarSystemList").empty();
    $.each(calendars.Calendars, function (idx, calendar) {
      $("#calendarSystemList").append(getCalendarFilterElement(calendar, "system"));
      addCalendarEventSource("system", calendar.Id);
    });
  });
}

function initializeFilterSettings() {
  clearAllEventSources();
  showAllUserCalendars();
  showAllSystemCalendars();
}

function initializeNewCalendarButton() {
  if (window.CRM.calendarJSArgs.isModifiable) {
    $("#addCalendarBtn").show().on("click", function () {
      window.newCalendarModal.show();
    });
  }
}

function displayAccessTokenAPITest() {
  if (
    window.CRM.calendarJSArgs.countCalendarAccessTokens > 0 &&
    !window.CRM.calendarJSArgs.bEnableExternalCalendarAPI
  ) {
    $("#calendarApiWarning").removeClass("d-none");
  }
}

document.addEventListener("DOMContentLoaded", function () {
  window.CRM.onLocalesReady(function () {
    initializeCalendar();
    initializeFilterSettings();
    initializeNewCalendarButton();
    registerCalendarSelectionEvents();
    displayAccessTokenAPITest();

    window.CRM.fullcalendar.render();
  });
});
