window.moveEventModal = {
  getButtons: (confirmLabel, confirmClass) => ({
      cancel: {
        label: `<i class="fa-solid fa-times me-1"></i>${i18next.t("Cancel")}`,
        className: "btn-secondary",
      },
      confirm: {
        label: `<i class="fa-solid fa-check me-1"></i>${i18next.t(confirmLabel || "Confirm")}`,
        className: confirmClass || "btn-primary",
      },
    }),
  buildMessage: (_eventTitle, fromLabel, fromDate, toLabel, toDate) => (
      '<p class="text-muted mb-3">' +
      i18next.t("Are you sure you want to continue?") +
      "</p>" +
      '<div class="d-flex align-items-stretch gap-2">' +
      '<div class="card flex-fill border-danger-subtle mb-0">' +
      '<div class="card-body py-2 px-3">' +
      '<div class="text-danger small fw-medium mb-1"><i class="fa-solid fa-calendar-xmark me-1"></i>' +
      i18next.t(fromLabel) +
      "</div>" +
      '<div class="fw-semibold">' +
      fromDate +
      "</div>" +
      "</div></div>" +
      '<div class="d-flex align-items-center text-muted px-1"><i class="fa-solid fa-arrow-right"></i></div>' +
      '<div class="card flex-fill border-success-subtle mb-0">' +
      '<div class="card-body py-2 px-3">' +
      '<div class="text-success small fw-medium mb-1"><i class="fa-solid fa-calendar-check me-1"></i>' +
      i18next.t(toLabel) +
      "</div>" +
      '<div class="fw-semibold">' +
      toDate +
      "</div>" +
      "</div></div>" +
      "</div>"
    ),
  modalCallBack: (result) => {
    if (result === true) {
      const evt = window.moveEventModal.event;
      window.CRM.APIRequest({
        method: "POST",
        path: `events/${evt.id}/time`,
        data: JSON.stringify({
          startTime: evt.allDay ? evt.startStr : evt.start.toISOString(),
          endTime: evt.end ? evt.end.toISOString() : null,
        }),
      });
    } else {
      window.moveEventModal.revertFunc();
    }
  },
  handleEventDrop: (info) => {
    const event = info.event;
    const revertFunc = info.revert;
    const originalStart = info.oldEvent.start ? info.oldEvent.start.toLocaleString() : info.oldEvent.startStr;
    const newStart = event.start ? event.start.toLocaleString() : event.startStr;
    window.moveEventModal.revertFunc = revertFunc;
    window.moveEventModal.event = event;
    bootbox.confirm({
      title: `<i class="fa-solid fa-calendar-arrow-up me-2 text-primary"></i>${event.title}`,
      message: window.moveEventModal.buildMessage(event.title, "From", originalStart, "To", newStart),
      buttons: window.moveEventModal.getButtons("Move"),
      callback: window.moveEventModal.modalCallBack,
      className: "modal-sm",
    });
  },
  handleEventResize: (info) => {
    const event = info.event;
    const revertFunc = info.revert;
    const originalEnd = info.oldEvent.end
      ? info.oldEvent.end.toLocaleString()
      : info.oldEvent.endStr || info.oldEvent.startStr;
    const newEnd = event.end ? event.end.toLocaleString() : event.endStr || event.startStr;
    window.moveEventModal.revertFunc = revertFunc;
    window.moveEventModal.event = event;
    bootbox.confirm({
      title: `<i class="fa-solid fa-clock me-2 text-primary"></i>${event.title}`,
      message: window.moveEventModal.buildMessage(event.title, "Old End", originalEnd, "New End", newEnd),
      buttons: window.moveEventModal.getButtons("Resize"),
      callback: window.moveEventModal.modalCallBack,
      className: "modal-sm",
    });
  },
};

window.CRM.refreshAllFullCalendarSources = () => {
  window.CRM.fullcalendar.refetchEvents();
};

function deleteCalendar() {
  window.CRM.APIRequest({
    method: "DELETE",
    path: `calendars/${window.calendarPropertiesModal.calendar.Id}`,
  }).done(() => {
    const eventSource = window.CRM.fullcalendar.getEventSourceById(`user-${window.calendarPropertiesModal.calendar.Id}`);
    if (eventSource) {
      eventSource.remove();
    }
    initializeFilterSettings();
    setTimeout(() => {
      window.location.reload();
    }, 1000);
  });
}

window.calendarPropertiesModal = {
  _copyToClipboard: (text) => {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
      navigator.clipboard
        .writeText(text)
        .then(() => {
          // brief visual feedback handled by btn animation
        })
        .catch(() => {
          // Fallback to old method if clipboard API fails
          const fallbackTextarea = document.createElement("textarea");
          fallbackTextarea.value = text;
          fallbackTextarea.setAttribute("readonly", "");
          fallbackTextarea.style.position = "absolute";
          fallbackTextarea.style.left = "-9999px";
          document.body.appendChild(fallbackTextarea);
          try {
            fallbackTextarea.select();
            document.execCommand("copy");
            document.body.removeChild(fallbackTextarea);
          } catch (_e) {
            document.body.removeChild(fallbackTextarea);
            if (typeof bootbox !== "undefined") {
              bootbox.alert(i18next.t("Unable to copy to clipboard. Please copy the URL manually."));
            }
          }
        });
    } else {
      // Fallback for non-secure contexts
      if (typeof bootbox !== "undefined") {
        bootbox.alert(i18next.t("Clipboard API not available. Please copy the URL manually."));
      }
    }
  },
  getBootboxContent: (calendar) => {
    let HTMLURL = "";
    let icsURL = "";
    let jsonURL = "";
    if (calendar.AccessToken) {
      HTMLURL = `${window.CRM.fullURL}/external/calendars/${calendar.AccessToken}`;
      icsURL = `${window.CRM.fullURL}/api/public/calendar/${calendar.AccessToken}/ics`;
      jsonURL = `${window.CRM.fullURL}/api/public/calendar/${calendar.AccessToken}/events`;
    }

    const copyBtn = (url) => (
        '<button type="button" class="btn btn-icon btn-ghost-secondary copy-url-btn" data-url="' +
        url +
        '" title="' +
        i18next.t("Copy to clipboard") +
        '">' +
        '<i class="fa-regular fa-copy"></i>' +
        "</button>"
      );

    const urlRow = (label, url) => {
      if (!url) return "";
      return (
        '<div class="mb-3">' +
        '<label class="form-label text-muted small mb-1">' +
        label +
        "</label>" +
        '<div class="input-group">' +
        '<input type="text" class="form-control form-control-sm font-monospace" readonly value="' +
        url +
        '">' +
        '<a href="' +
        url +
        '" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost-secondary" title="' +
        i18next.t("Open") +
        '">' +
        '<i class="fa-solid fa-arrow-up-right-from-square"></i>' +
        "</a>" +
        copyBtn(url) +
        "</div>" +
        "</div>"
      );
    };

    const colorSwatch = (hex) => (
        '<span class="d-inline-flex align-items-center gap-2">' +
        '<span style="display:inline-block;width:1.25rem;height:1.25rem;border-radius:4px;border:1px solid var(--tblr-border-color);background:#' +
        hex +
        '"></span>' +
        "<code>#" +
        hex +
        "</code>" +
        "</span>"
      );

    // Access token section
    let tokenSection =
      '<div class="mb-3">' +
      '<label class="form-label text-muted small mb-1">' +
      i18next.t("Access Token") +
      "</label>" +
      '<div class="input-group">' +
      '<input id="AccessToken" class="form-control form-control-sm font-monospace" type="text" readonly value="' +
      (calendar.AccessToken || "") +
      '">' +
      copyBtn(calendar.AccessToken || "") +
      "</div>";

    if (window.CRM.calendarJSArgs.isModifiable) {
      tokenSection +=
        '<div class="d-flex gap-2 mt-2">' +
        '<a id="NewAccessToken" class="btn btn-sm btn-outline-warning flex-fill">' +
        '<i class="fa-solid fa-repeat me-1"></i>' +
        i18next.t("New Access Token") +
        "</a>";
      if (calendar.AccessToken != null) {
        tokenSection +=
          '<a id="DeleteAccessToken" class="btn btn-sm btn-outline-danger">' +
          '<i class="fa-solid fa-trash-can me-1"></i>' +
          i18next.t("Delete") +
          "</a>";
      }
      tokenSection += "</div>";
    }
    tokenSection += "</div>";

    const frm_str =
      '<form id="some-form" class="px-1">' +
      tokenSection +
      urlRow(i18next.t("HTML URL"), HTMLURL) +
      urlRow(i18next.t("ICS URL"), icsURL) +
      urlRow("JSON URL", jsonURL) +
      '<div class="row g-3">' +
      '<div class="col-6">' +
      '<label class="form-label text-muted small mb-1">' +
      i18next.t("Foreground Color") +
      "</label>" +
      "<div>" +
      colorSwatch(calendar.ForegroundColor) +
      "</div>" +
      "</div>" +
      '<div class="col-6">' +
      '<label class="form-label text-muted small mb-1">' +
      i18next.t("Background Color") +
      "</label>" +
      "<div>" +
      colorSwatch(calendar.BackgroundColor) +
      "</div>" +
      "</div>" +
      "</div>" +
      "</form>";

    const $object = $("<div/>").html(frm_str).contents();

    // Wire up copy buttons after DOM insertion (bootbox calls this after render)
    // Use .off() first to prevent handler accumulation across modal re-opens
    setTimeout(() => {
      $(".copy-url-btn")
        .off("click")
        .on("click", function () {
          const url = $(this).data("url");
          window.calendarPropertiesModal._copyToClipboard(url);
          const $icon = $(this).find("i");
          $icon.removeClass("fa-regular fa-copy").addClass("fa-solid fa-check text-success");
          setTimeout(() => {
            $(this).find("i").removeClass("fa-solid fa-check text-success").addClass("fa-regular fa-copy");
          }, 1500);
        });
    }, 50);

    return $object;
  },
  getButtons: () => {
    const buttons = [];
    buttons.push({
      label: i18next.t("Cancel"),
      className: "btn btn-secondary float-end",
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
  show: (calendar) => {
    window.calendarPropertiesModal.calendar = calendar;
    const bootboxmessage = window.calendarPropertiesModal.getBootboxContent(calendar);
    window.calendarPropertiesModal.modal = bootbox.dialog({
      title: calendar.Name,
      message: bootboxmessage,
      show: true,
      buttons: window.calendarPropertiesModal.getButtons(),
      onEscape: () => {
        window.calendarPropertiesModal.modal.modal("hide");
      },
    });
    $("#NewAccessToken").click(window.calendarPropertiesModal.newAccessToken);
    $("#DeleteAccessToken").click(window.calendarPropertiesModal.deleteAccessToken);
  },
  newAccessToken: () => {
    window.CRM.APIRequest({
      method: "POST",
      path: `calendars/${window.calendarPropertiesModal.calendar.Id}/NewAccessToken`,
    }).done((newcalendar) => {
      const closeBtn = $(`<button class="btn btn-primary float-end mt-3">${i18next.t("Close")}</button>`);
      const $body = $(window.calendarPropertiesModal.modal).find(".bootbox-body");
      $body.html(window.calendarPropertiesModal.getBootboxContent(newcalendar));
      $body.append(closeBtn);
      $("#NewAccessToken").click(window.calendarPropertiesModal.newAccessToken);
      $("#DeleteAccessToken").click(window.calendarPropertiesModal.deleteAccessToken);
      closeBtn.on("click", () => {
        window.calendarPropertiesModal.modal.modal("hide");
      });

      const calendarRow = $(`[data-calendarid="${newcalendar.Id}"]`).closest("tr");
      if (calendarRow.length > 0) {
        const accessTokenCell = calendarRow.find(".calendar-access-token-cell");
        if (accessTokenCell.length > 0) {
          accessTokenCell.text(newcalendar.AccessToken);
        }
      }

      bootbox.alert(i18next.t("A Calendar access token has been generated and saved."));
      window.calendarPropertiesModal.calendar = newcalendar;
    });
  },
  deleteAccessToken: () => {
    window.CRM.APIRequest({
      method: "DELETE",
      path: `calendars/${window.calendarPropertiesModal.calendar.Id}/AccessToken`,
    }).done((newcalendar) => {
      const calendarRow = $(`[data-calendarid="${newcalendar.Id}"]`).closest("tr");
      if (calendarRow.length > 0) {
        const accessTokenCell = calendarRow.find(".calendar-access-token-cell");
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

      setTimeout(() => {
        document.body.focus();
      }, 300);
    });
  },
};

function fieldError(inputField) {
  let p = $(inputField).parent().find("p .form-field-error");
  if (p.length === 0) {
    p = $("<p class='form-field-error'>");
    $(inputField).parent().append(p);
  }
  $(p).text(i18next.t("Invalid Entry"));
}

window.newCalendarModal = {
  getBootboxContent: () => {
    const frm_str =
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
      '<input id="ForegroundColor" class="form-control form-control-color w-100" type="color" value="#ffffff" title="' +
      i18next.t("Foreground Color") +
      '" />' +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td>" +
      i18next.t("Background Color") +
      ":</td>" +
      '<td colspan="3">' +
      '<input id="BackgroundColor" class="form-control form-control-color w-100" type="color" value="#000000" title="' +
      i18next.t("Background Color") +
      '" />' +
      "</td>" +
      "</tr>" +
      "</table>" +
      "</form>";
    const object = $("<div/>").html(frm_str).contents();

    return object;
  },
  getButtons: () => {
    const buttons = [];
    buttons.push({
      label: i18next.t("Save"),
      className: "btn btn-primary float-end",
      callback: window.newCalendarModal.saveButtonCallback,
    });
    buttons.push({
      label: i18next.t("Cancel"),
      className: "btn btn-secondary float-start",
    });

    return buttons;
  },
  validateNewCalendar: () => {
    let status = true;
    if (!$("#calendarName").val()) {
      fieldError($("#calendarName"));
      status = false;
    }
    return status;
  },
  saveButtonCallback: () => {
    $(".form-field-error").remove();
    if (!window.newCalendarModal.validateNewCalendar()) {
      return false;
    }
    const newCalendar = {
      Name: $("#calendarName").val(),
      ForegroundColor: $("#ForegroundColor").val().replace(/^#/, ""),
      BackgroundColor: $("#BackgroundColor").val().replace(/^#/, ""),
    };
    window.CRM.APIRequest({
      method: "POST",
      path: "calendars",
      data: JSON.stringify(newCalendar),
    }).done(() => {
      initializeFilterSettings();
    });
  },
  show: () => {
    const bootboxmessage = window.newCalendarModal.getBootboxContent();
    window.calendarPropertiesModal.modal = bootbox.dialog({
      title: i18next.t("New Calendar"),
      message: bootboxmessage,
      show: true,
      buttons: window.newCalendarModal.getButtons(),
      onEscape: () => {
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

  const mobileHeaderToolbar = {
    start: "prev,next",
    center: "title",
    end: "today",
  };
  const desktopHeaderToolbar = {
    start: "prev,next today",
    center: "title",
    end: "dayGridMonth,timeGridWeek,timeGridDay,listMonth",
  };
  const mobileFooterToolbar = { end: "dayGridMonth,timeGridWeek,timeGridDay,listMonth" };

  window.CRM.fullcalendar = new FullCalendar.Calendar(document.getElementById("calendar"), {
    locale: window.CRM.lang || "en",
    timeZone: window.CRM.calendarJSArgs.sTimeZone || "local",
    headerToolbar: window.innerWidth < 768 ? mobileHeaderToolbar : desktopHeaderToolbar,
    footerToolbar: window.innerWidth < 768 ? mobileFooterToolbar : false,
    contentHeight: "auto",
    windowResizeDelay: 200,
    windowResize: () => {
      const nowMobile = window.innerWidth < 768;
      window.CRM.fullcalendar.setOption("headerToolbar", nowMobile ? mobileHeaderToolbar : desktopHeaderToolbar);
      window.CRM.fullcalendar.setOption("footerToolbar", nowMobile ? mobileFooterToolbar : false);
    },
    selectable: true,
    editable: window.CRM.calendarJSArgs.isModifiable,
    eventDrop: window.moveEventModal.handleEventDrop,
    eventResize: window.moveEventModal.handleEventResize,
    selectMirror: true,
    select: window.showNewEventForm,
    eventClick: (info) => {
      const eventData = info.event;
      const jsEvent = info.jsEvent;
      jsEvent.preventDefault();

      if (eventData.url !== "null") {
        window.open(eventData.url);
      } else if (eventData.editable || eventData.startEditable || eventData.durationEditable) {
        window.showEventForm(eventData);
      } else {
        alert(`${i18next.t("Holiday")}: ${eventData.title}`);
      }
    },
    loading: (isLoading) => {
      window.CRM.isCalendarLoading = isLoading;
    },
  });
}

/**
 * Build a stable event source URL using a deterministic source ID.
 * No cache-busting parameter — FullCalendar refetches via refetchEvents().
 */
function GetCalendarURL(calendarType, calendarID) {
  let endpoint;
  if (calendarType === "user") {
    endpoint = "/api/calendars/";
  } else if (calendarType === "system") {
    endpoint = "/api/systemcalendars/";
  }
  return `${window.CRM.root + endpoint + calendarID}/fullcalendar`;
}

/**
 * Get the deterministic event source ID for a calendar.
 */
function GetCalendarSourceId(calendarType, calendarID) {
  return `${calendarType}-${calendarID}`;
}

/**
 * Build a sidebar list-group item for a calendar with a BS5 form-switch toggle.
 */
function getCalendarFilterElement(calendar, type) {
  const sourceId = GetCalendarSourceId(type, calendar.Id);
  const switchId = `display-${sourceId}`;

  const publicBadge = calendar.AccessToken
    ? '<span class="badge bg-azure-lt ms-1" title="' +
      i18next.t("Public calendar") +
      '"><i class="fa-solid fa-globe fa-xs"></i></span>'
    : "";

  const html =
    '<div class="list-group-item px-2 py-2" data-source-id="' +
    sourceId +
    '">' +
    '<div class="d-flex align-items-center">' +
    '<span class="d-inline-block rounded-circle flex-shrink-0 me-2" style="width:12px;height:12px;background-color:#' +
    calendar.BackgroundColor +
    '"></span>' +
    '<div class="flex-fill">' +
    '<div class="fw-medium small d-flex align-items-center">' +
    calendar.Name +
    publicBadge +
    "</div>" +
    "</div>" +
    '<div class="form-check form-switch ms-2 mb-0">' +
    '<input type="checkbox" class="form-check-input calendarSelectionBox" id="' +
    switchId +
    '" checked data-calendartype="' +
    type +
    '" data-calendarid="' +
    calendar.Id +
    '" aria-label="Toggle ' +
    calendar.Name +
    ' calendar visibility"/>' +
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
  const sources = window.CRM.fullcalendar.getEventSources();
  for (let i = sources.length - 1; i >= 0; i--) {
    sources[i].remove();
  }
}

/**
 * Add an event source with a deterministic ID (prevents duplicates).
 */
function addCalendarEventSource(calendarType, calendarID) {
  const sourceId = GetCalendarSourceId(calendarType, calendarID);
  const existing = window.CRM.fullcalendar.getEventSourceById(sourceId);
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
  const sourceId = GetCalendarSourceId(calendarType, calendarID);
  const existing = window.CRM.fullcalendar.getEventSourceById(sourceId);
  if (existing) {
    existing.remove();
  }
}

function registerCalendarSelectionEvents() {
  $(document).on("change", ".calendarSelectionBox", function () {
    const calendarType = $(this).data("calendartype");
    const calendarId = $(this).data("calendarid");

    if ($(this).is(":checked")) {
      addCalendarEventSource(calendarType, calendarId);
    } else {
      removeCalendarEventSource(calendarType, calendarId);
    }
  });

  $(document).on("click", ".calendarproperties", function () {
    window.CRM.APIRequest({
      method: "GET",
      path: `calendars/${$(this).data("calendarid")}`,
    }).done((data) => {
      const calendar = data.Calendars?.[0] ? data.Calendars[0] : null;
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
    const calendarTypeToKeep = $(this).data("calendartype");
    const calendarIDToKeep = $(this).data("calendarid");
    $(".calendarSelectionBox").each(function () {
      if ($(this).data("calendartype") === calendarTypeToKeep && $(this).data("calendarid") === calendarIDToKeep) {
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
    $(this).html(`<i class="fa-solid fa-expand me-1"></i>${i18next.t("Unfocus")}`);
  });

  $(document).on("click", ".calendarunfocus", function () {
    $(".calendarSelectionBox").each(function () {
      if (!$(this).is(":checked")) {
        $(this).prop("checked", true).trigger("change");
      }
    });
    $(this).removeClass("calendarunfocus").addClass("calendarfocus");
    $(this).html(`<i class="fa-solid fa-crosshairs me-1"></i>${i18next.t("Focus")}`);
  });
}

function showAllUserCalendars() {
  window.CRM.APIRequest({
    method: "GET",
    path: "calendars",
    suppressErrorDialog: true,
  }).done((calendars) => {
    $("#calendarUserList").empty();
    $.each(calendars.Calendars, (_idx, calendar) => {
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
  }).done((calendars) => {
    $("#calendarSystemList").empty();
    $.each(calendars.Calendars, (_idx, calendar) => {
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
    $("#addCalendarBtn")
      .removeClass("d-none")
      .on("click", () => {
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

document.addEventListener("DOMContentLoaded", () => {
  window.CRM.onLocalesReady(() => {
    initializeCalendar();
    initializeFilterSettings();
    initializeNewCalendarButton();
    registerCalendarSelectionEvents();
    displayAccessTokenAPITest();

    window.CRM.fullcalendar.render();
  });
});
