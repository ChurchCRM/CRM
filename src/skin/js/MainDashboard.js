/**
 * Main Dashboard initialization script
 * Requires: moment.js (loaded globally), i18next, DataTables, ApexCharts
 */

export function initializeMainDashboard() {
  // Use the global jQuery which has DataTables and other plugins attached.
  // Do NOT use `import $ from "jquery"` — that creates a separate instance
  // inside this webpack entry bundle, without the plugins.
  const $ = window.jQuery;

  // Guard against jQuery not being loaded yet
  if (!$ || !$.extend) {
    console.error(
      "jQuery with plugins not available - skin-main.js may not have loaded yet. mainDashboard initialization deferred.",
    );
    // Retry after a short delay to allow skin-main.js to load
    setTimeout(() => initializeMainDashboard(), 500);
    return;
  }
  // Helper to generate Tabler simple avatar with initials (not clickable)
  function generateTablerAvatar(name, id, type = "person") {
    const parts = name.trim().split(/\s+/);
    let initials = "";
    if (parts.length >= 2) {
      initials = (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    } else if (parts.length === 1) {
      initials = parts[0].substring(0, 2).toUpperCase();
    }

    // Deterministic color based on name
    const colors = ["#667eea", "#764ba2", "#f093fb", "#4facfe", "#00f2fe", "#43e97b", "#fa709a", "#fee140"];
    const hash = name.split("").reduce((acc, char) => acc + char.charCodeAt(0), 0);
    const color = colors[hash % colors.length];

    return `<span class="avatar avatar-sm rounded-circle" style="background-color: ${color};"><span class="avatar-title fs-6 fw-bold">${initials}</span></span>`;
  }

  // Helper to generate clickable photo img when row.HasPhoto === true
  function generatePhotoImg(id, type) {
    const viewClass = type === "person" ? "view-person-photo" : "view-family-photo";
    const dataIdAttr = type === "person" ? `data-person-id="${id}"` : `data-family-id="${id}"`;
    const photoUrl = window.CRM.root + `/api/${type}/${id}/photo`;
    return `<img class="avatar avatar-sm rounded-circle ${viewClass}" src="${photoUrl}" ${dataIdAttr} alt="" style="cursor: pointer; object-fit: cover;" title="${i18next.t("View Photo")}" />`;
  }

  const dataTableDashboardDefaults = {
    paging: false,
    ordering: false,
    info: false,
    layout: {
      topStart: null,
      topEnd: null,
      bottomStart: null,
      bottomEnd: null,
    },
  };

  // Define action column for families and base columns without action
  const actionFamilyColumn = {
    width: "15%",
    sortable: false,
    title: i18next.t("Action"),
    data: "FamilyId",
    className: "no-export",
    render: (data, type, row) => window.CRM.renderFamilyActionMenu(row.FamilyId, row.Name),
    searchable: false,
  };

  const dataTableFamilyColumns = [
    {
      width: "35%",
      title: i18next.t("Name"),
      data: "Name",
      render: (data, type, row) => {
        // Show photo if available, otherwise show Tabler avatar with initials
        var photoIcon = row.HasPhoto
          ? generatePhotoImg(row.FamilyId, "family")
          : generateTablerAvatar(row.Name, row.FamilyId, "family");
        photoIcon += " ";

        // Render status badge only for inactive families
        let statusHtml = "";
        if (row.StatusText && row.IsActive === false) {
          statusHtml =
            ' <span class="badge bg-secondary-lt text-secondary" title="' +
            i18next.t("Inactive") +
            '"><i class="ti ti-power me-1"></i>' +
            i18next.t("Inactive") +
            "</span>";
        }

        return (
          '<div class="d-flex align-items-center gap-2">' +
          photoIcon +
          ' <a href="' +
          window.CRM.root +
          "/v2/family/" +
          row.FamilyId +
          '"><strong>' +
          row.Name +
          "</strong></a>" +
          statusHtml +
          "</div>"
        );
      },
    },
    {
      width: "30%",
      title: i18next.t("Location"),
      data: "Address",
      render: (data, type, row) => {
        if (!data) return '<span class="text-muted">—</span>';
        // Extract city and state from address (last parts before country)
        const parts = data.split(",").map((s) => s.trim());
        if (parts.length >= 2) {
          // Try to get city and state (usually 2nd and 3rd from end, before country)
          const cityState = parts.slice(-3, -1).join(", ");
          if (cityState) {
            return '<span title="' + data + '">' + cityState + "</span>";
          }
        }
        return '<span title="' + data + '">' + data.substring(0, 30) + (data.length > 30 ? "..." : "") + "</span>";
      },
    },
  ];

  const latestFamilyColumns = dataTableFamilyColumns.slice();
  latestFamilyColumns.push({
    width: "20%",
    title: i18next.t("Created"),
    data: "Created",
    render: (data) => {
      if (!data) return "";
      // Parse datetime format and calculate relative time
      return '<small class="text-muted">' + moment(data).fromNow() + "</small>";
    },
  });
  // Put action column last per new standard
  latestFamilyColumns.push(actionFamilyColumn);

  let dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/families/latest",
      dataSrc: "families",
    },
    columns: latestFamilyColumns,
  };
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  $.extend(dataTableConfig, dataTableDashboardDefaults);
  const latestFamiliesTable = $("#latestFamiliesDashboardItem").DataTable(dataTableConfig);
  latestFamiliesTable.on("draw", () => {
    syncCartButtons();
  });

  const updatedFamilyColumns = dataTableFamilyColumns.slice();
  updatedFamilyColumns.push({
    width: "20%",
    title: i18next.t("Updated"),
    data: "LastEdited",
    render: (data) => {
      if (!data) return "";
      // Parse datetime format and calculate relative time
      return '<small class="text-muted">' + moment(data).fromNow() + "</small>";
    },
  });
  // Put action column last per new standard
  updatedFamilyColumns.push(actionFamilyColumn);

  dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/families/updated",
      dataSrc: "families",
    },
    columns: updatedFamilyColumns,
  };
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  $.extend(dataTableConfig, dataTableDashboardDefaults);
  const updatedFamiliesTable = $("#updatedFamiliesDashboardItem").DataTable(dataTableConfig);
  updatedFamiliesTable.on("draw", () => {
    syncCartButtons();
  });

  dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/persons/birthday",
      dataSrc: (json) => {
        if (!json.people || json.people.length === 0) {
          $("#PersonBirthdayDashboardItem")
            .closest(".card-body")
            .html(
              '<div class="empty py-4">' +
                '<div class="empty-icon"><i class="fa-solid fa-cake-candles fa-2x text-muted"></i></div>' +
                '<p class="empty-title">' +
                i18next.t("No Birthdays") +
                "</p>" +
                '<p class="empty-subtitle text-muted">' +
                i18next.t("No birthdays in the past or next 7 days") +
                "</p>" +
                "</div>",
            );
          return [];
        }
        return json.people;
      },
    },
    columns: [
      {
        width: "40px",
        title: "",
        data: "PersonId",
        orderable: false,
        className: "text-center",
        render: (data, type, row) => "",
      },
      {
        width: "60%",
        title: i18next.t("Name"),
        data: "FirstName",
        render: (data, type, row) => {
          // The Birthdays widget lives in a narrow sidebar column at every
          // breakpoint (mobile, tablet, and desktop xl-sidebar). Renders MUST
          // tolerate ~120px of name space without breaking layout.
          //
          // The server returns Age as a localized long string like
          // "66 years old". Re-format here as "66 yrs" on its own line so:
          //   1. the name never has to share a line with the age,
          //   2. wrapping happens between name and age, not mid-phrase,
          //   3. the column stays compact at every viewport.
          let ageText = "";
          if (row.Age) {
            const match = String(row.Age).match(/\d+/);
            if (match) {
              ageText = '<div class="text-muted small lh-1 mt-1">' + match[0] + " " + i18next.t("yrs") + "</div>";
            }
          }
          // Show photo if available, otherwise show Tabler avatar with initials
          const photoIcon = row.HasPhoto
            ? generatePhotoImg(row.PersonId, "person")
            : generateTablerAvatar(row.FormattedName, row.PersonId, "person");
          return (
            '<div class="d-flex align-items-center gap-2">' +
            photoIcon +
            '<div class="min-w-0 flex-grow-1"><a class="text-break" href="' +
            window.CRM.root +
            "/PersonView.php?PersonID=" +
            row.PersonId +
            '"><strong>' +
            row.FormattedName +
            "</strong></a>" +
            ageText +
            "</div></div>"
          );
        },
      },
      {
        width: "40%",
        title: i18next.t("Birthday"),
        data: "DaysUntil",
        render: (data, type, row) => {
          if (row.Birthday === undefined) return "";
          const diff = row.DaysUntil;

          let badge = "";
          if (diff === 0) {
            badge = '<span class="badge bg-success-lt text-success">' + i18next.t("Today") + "!</span>";
          } else if (diff > 0) {
            badge =
              '<span class="badge bg-info-lt text-info">' +
              i18next.t("in") +
              " " +
              diff +
              " " +
              (diff === 1 ? i18next.t("day") : i18next.t("days")) +
              "</span>";
          } else {
            badge =
              '<span class="badge bg-secondary-lt text-secondary">' +
              Math.abs(diff) +
              " " +
              (Math.abs(diff) === 1 ? i18next.t("day") : i18next.t("days")) +
              " " +
              i18next.t("ago") +
              "</span>";
          }
          return row.Birthday + " " + badge;
        },
      },
    ],
    // Paginate birthdays after 5 items
    paging: true,
    pageLength: 5,
  };
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  $.extend(dataTableConfig, dataTableDashboardDefaults);
  // Ensure paging settings aren't overridden by dashboard defaults
  dataTableConfig.paging = true;
  dataTableConfig.pageLength = 5;
  // Include pagination control in DOM (dashboard defaults remove it)
  dataTableConfig.dom = "<'row'<'col-sm-12'tr>><'row'<'col-sm-12'p>>";
  const birthdayPersonTable = $("#PersonBirthdayDashboardItem").DataTable(dataTableConfig);
  birthdayPersonTable.on("draw", () => {
    syncCartButtons();
    // Refresh image loader for dynamically added photos
    if (window.CRM && window.CRM.peopleImageLoader) {
      window.CRM.peopleImageLoader.refresh();
    }
  });

  dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/families/anniversaries",
      dataSrc: (json) => {
        if (!json.families || json.families.length === 0) {
          $("#FamiliesWithAnniversariesDashboardItem")
            .closest(".card-body")
            .html(
              '<div class="empty py-4">' +
                '<div class="empty-icon"><i class="fa-solid fa-heart fa-2x text-muted"></i></div>' +
                '<p class="empty-title">' +
                i18next.t("No Anniversaries") +
                "</p>" +
                '<p class="empty-subtitle text-muted">' +
                i18next.t("No anniversaries in the past or next 7 days") +
                "</p>" +
                "</div>",
            );
          return [];
        }
        return json.families;
      },
    },
    columns: [
      {
        width: "50%",
        title: i18next.t("Name"),
        data: "Name",
        render: (data, type, row) => {
          // Anniversaries widget shares the narrow sidebar column with the
          // Birthdays widget — keep the markup symmetrical (gap-2, min-w-0,
          // text-break) so long family names wrap cleanly at every breakpoint.
          const photoIcon = row.HasPhoto
            ? generatePhotoImg(row.FamilyId, "family")
            : generateTablerAvatar(data, row.FamilyId, "family");
          return (
            '<div class="d-flex align-items-center gap-2">' +
            photoIcon +
            '<div class="min-w-0 flex-grow-1"><a class="text-break" href="' +
            window.CRM.root +
            "/v2/family/" +
            row.FamilyId +
            '"><strong>' +
            data +
            "</strong></a></div></div>"
          );
        },
      },
      {
        width: "50%",
        title: i18next.t("Anniversary"),
        data: "WeddingDate",
        render: (data, type, row) => {
          if (!data) return "";
          const weddingDate = moment(data, ["MMMM D, YYYY", "MMMM D", "MM-DD-YYYY"]);
          const thisYear = moment().year();
          const anniversaryThisYear = weddingDate.clone().year(thisYear);
          const today = moment().startOf("day");
          const diff = anniversaryThisYear.diff(today, "days");
          const years = thisYear - weddingDate.year();

          let badge = "";
          if (diff === 0) {
            badge =
              '<span class="badge bg-success-lt text-success ms-2">' +
              years +
              " " +
              i18next.t("years") +
              " " +
              i18next.t("Today") +
              "!</span>";
          } else if (diff > 0) {
            badge =
              '<span class="badge bg-info-lt text-info ms-2">' +
              i18next.t("in") +
              " " +
              diff +
              " " +
              (diff === 1 ? i18next.t("day") : i18next.t("days")) +
              "</span>";
          } else {
            badge =
              '<span class="badge bg-secondary-lt text-secondary ms-2">' +
              Math.abs(diff) +
              " " +
              (Math.abs(diff) === 1 ? i18next.t("day") : i18next.t("days")) +
              " " +
              i18next.t("ago") +
              "</span>";
          }
          return data + badge;
        },
      },
    ],
    // Paginate anniversaries after 5 items
    paging: true,
    pageLength: 5,
  };
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  $.extend(dataTableConfig, dataTableDashboardDefaults);
  // Ensure paging settings aren't overridden by dashboard defaults
  dataTableConfig.paging = true;
  dataTableConfig.pageLength = 5;
  // Include pagination control in DOM (dashboard defaults remove it)
  dataTableConfig.dom = "<'row'<'col-sm-12'tr>><'row'<'col-sm-12'p>>";
  const anniversaryFamiliesTable = $("#FamiliesWithAnniversariesDashboardItem").DataTable(dataTableConfig);
  anniversaryFamiliesTable.on("draw", () => {
    syncCartButtons();
    if (window.CRM && window.CRM.peopleImageLoader) {
      window.CRM.peopleImageLoader.refresh();
    }
  });

  // Define action column for persons and base columns without action
  const actionPersonColumn = {
    width: "15%",
    sortable: false,
    title: i18next.t("Action"),
    data: "PersonId",
    className: "no-export",
    render: (data, type, row) =>
      window.CRM.renderPersonActionMenu(row.PersonId, row.FirstName + " " + row.LastName, {
        familyId: row.FamilyId || null,
      }),
    searchable: false,
  };

  const dataTablePersonColumns = [
    {
      width: "25%",
      title: i18next.t("Name"),
      data: "FirstName",
      render: (data, type, row) => {
        // Show photo if available, otherwise show Tabler avatar with initials
        var photoIcon = row.HasPhoto
          ? generatePhotoImg(row.PersonId, "person")
          : generateTablerAvatar(row.FirstName + " " + row.LastName, row.PersonId, "person");
        photoIcon += " ";
        return (
          '<div class="d-flex align-items-center gap-2">' +
          photoIcon +
          ' <a href="' +
          window.CRM.root +
          "/PersonView.php?PersonID=" +
          row.PersonId +
          '"><strong>' +
          row.FirstName +
          " " +
          row.LastName +
          "</strong></a></div>"
        );
      },
    },
    {
      width: "25%",
      title: i18next.t("Family"),
      data: "FamilyName",
      render: (data, type, row) => {
        if (!row.FamilyId || !row.FamilyName) {
          return '<span class="text-muted">—</span>';
        }
        // Render inactive status badge for the person's family if present
        let statusHtml = "";
        // Support both flattened family fields and unprefixed ones
        if ((row.FamilyStatusText && row.FamilyIsActive === false) || (row.StatusText && row.IsActive === false)) {
          statusHtml =
            ' <span class="badge bg-secondary-lt text-secondary" title="' +
            i18next.t("Inactive") +
            '"><i class="ti ti-power me-1"></i>' +
            i18next.t("Inactive") +
            "</span>";
        }
        return (
          '<a href="' + window.CRM.root + "/v2/family/" + row.FamilyId + '">' + row.FamilyName + "</a>" + statusHtml
        );
      },
    },
  ];

  const updatedPersonColumns = dataTablePersonColumns.slice();
  updatedPersonColumns.push({
    width: "20%",
    title: i18next.t("Updated"),
    data: "LastEdited",
    render: (data) => {
      if (!data) return "";
      // Parse datetime format and calculate relative time
      return '<small class="text-muted">' + moment(data).fromNow() + "</small>";
    },
  });

  // Put action column last per new standard
  updatedPersonColumns.push(actionPersonColumn);

  dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/persons/updated",
      dataSrc: "people",
    },
    columns: updatedPersonColumns,
  };
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  $.extend(dataTableConfig, dataTableDashboardDefaults);
  const updatedPersonTable = $("#updatedPersonDashboardItem").DataTable(dataTableConfig);
  updatedPersonTable.on("draw", () => {
    syncCartButtons();
    // No need to refresh image loader; inline photos have been removed
  });

  const latestPersonColumns = dataTablePersonColumns.slice();
  latestPersonColumns.push({
    width: "20%",
    title: i18next.t("Created"),
    data: "Created",
    render: (data) => {
      if (!data) return "";
      // Parse datetime format and calculate relative time
      return '<small class="text-muted">' + moment(data).fromNow() + "</small>";
    },
  });
  // Put action column last per new standard
  latestPersonColumns.push(actionPersonColumn);

  dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/persons/latest",
      dataSrc: "people",
    },
    columns: latestPersonColumns,
  };
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  $.extend(dataTableConfig, dataTableDashboardDefaults);
  const latestPersonTable = $("#latestPersonDashboardItem").DataTable(dataTableConfig);
  latestPersonTable.on("draw", () => {
    syncCartButtons();
    // Refresh image loader for dynamically added photos
    if (window.CRM && window.CRM.peopleImageLoader) {
      window.CRM.peopleImageLoader.refresh();
    }
  });
  function syncCartButtons() {
    if (window.CRM && window.CRM.cartManager) {
      Promise.all([
        window.CRM.APIRequest({
          method: "GET",
          path: "cart/",
          suppressErrorDialog: true,
        }),
        window.CRM.APIRequest({
          method: "GET",
          path: "families/familiesInCart",
          suppressErrorDialog: true,
        }),
      ]).then((responses) => {
        const cartData = responses[0];
        const familiesData = responses[1];

        const peopleInCart = cartData.PeopleCart || [];
        const familiesInCart = familiesData.familiesInCart || [];
        const groupsInCart = cartData.GroupCart || [];

        window.CRM.cartManager.syncButtonStates(peopleInCart, familiesInCart, groupsInCart);
      });
    }
  }

  function buildRenderEmail(email) {
    if (email) {
      return "<a href='mailto:" + email + "' target='_blank' rel='noopener noreferrer'>" + email + "</a>";
    }
    return "";
  }

  // Today's Events widget
  if ($("#todayEventsDashboardItem").length > 0) {
    const todayEventsConfig = {
      ajax: {
        url: window.CRM.root + "/api/events/today",
        dataSrc: (json) => {
          if (!json.events || json.events.length === 0) {
            $("#todayEventsDashboardItem")
              .closest(".card-body")
              .html(
                '<div class="empty py-4">' +
                  '<div class="empty-icon"><i class="fa-solid fa-calendar-day fa-2x text-muted"></i></div>' +
                  '<p class="empty-title">' +
                  i18next.t("No Events Today") +
                  "</p>" +
                  '<p class="empty-subtitle text-muted">' +
                  i18next.t("There are no events scheduled for today") +
                  "</p>" +
                  "</div>",
              );
            return [];
          }
          return json.events;
        },
      },
      columns: [
        {
          width: "40%",
          title: i18next.t("Event"),
          data: "title",
          render: (data, type, row) =>
            '<a href="' +
            window.CRM.root +
            "/event/view/" +
            row.id +
            '"><strong>' +
            window.CRM.escapeHtml(data) +
            "</strong></a>",
        },
        {
          width: "20%",
          title: i18next.t("Type"),
          data: "typeName",
          render: (data) => {
            if (!data) return "";
            return '<span class="badge bg-blue-lt">' + window.CRM.escapeHtml(data) + "</span>";
          },
        },
        {
          width: "15%",
          title: i18next.t("Time"),
          data: "start",
          render: (data) => {
            if (!data) return "";
            return '<small class="text-muted">' + moment(data).format("h:mm A") + "</small>";
          },
        },
        {
          width: "15%",
          title: i18next.t("Attendance"),
          data: "checkedIn",
          render: (data, type, row) => {
            const total = row.totalAttendees || 0;
            const checked = data || 0;
            if (total === 0 && checked === 0) {
              return '<span class="text-muted">—</span>';
            }
            const badgeClass = checked > 0 ? "bg-green-lt" : "bg-secondary-lt";
            return '<span class="badge ' + badgeClass + '">' + checked + " / " + total + "</span>";
          },
        },
        {
          width: "10%",
          title: i18next.t("Action"),
          data: "id",
          orderable: false,
          className: "no-export",
          // Today's Events only returns active events (filterByInActive(0))
          // so inactive is always false here.
          render: (data, type, row) => window.CRM.renderEventActionMenu(data, row.title, { inactive: false }),
        },
      ],
    };
    $.extend(todayEventsConfig, window.CRM.plugin.dataTable);
    $.extend(todayEventsConfig, dataTableDashboardDefaults);
    $("#todayEventsDashboardItem").DataTable(todayEventsConfig);
  }

  if ($("#depositChartRow").is(":visible")) {
    window.CRM.APIRequest({
      method: "GET",
      path: "deposits/dashboard",
    }).done((data) => {
      const lineDataRaw = data;

      if (!lineDataRaw || lineDataRaw.length === 0) {
        $("#depositChartRow .card-body").html(
          '<div class="empty py-4">' +
            '<div class="empty-icon"><i class="fa-solid fa-circle-dollar-to-slot fa-2x text-muted"></i></div>' +
            '<p class="empty-title">' +
            i18next.t("No Deposits") +
            "</p>" +
            '<p class="empty-subtitle text-muted">' +
            i18next.t("No deposit data available yet") +
            "</p>" +
            "</div>",
        );
        return;
      }

      const labels = [];
      const values = [];
      $.each(lineDataRaw, (i, val) => {
        labels.push(moment(val.Date).format("MM-DD-YY"));
        values.push(val.totalAmount);
      });

      const depositChartOptions = {
        chart: {
          type: "line",
          height: 250,
          sparkline: {
            enabled: false,
          },
          toolbar: {
            show: true,
            tools: {
              download: true,
              selection: true,
              zoom: true,
              zoomin: true,
              zoomout: true,
              reset: true,
            },
          },
        },
        series: [
          {
            name: i18next.t("Deposit Value"),
            data: values,
          },
        ],
        xaxis: {
          categories: labels,
        },
        yaxis: {
          title: {
            text: i18next.t("Amount"),
          },
        },
        stroke: {
          curve: "smooth",
          width: 2,
        },
        grid: {
          show: true,
          borderColor: "#e0e0e0",
        },
      };

      const depositChartElement = document.getElementById("deposit-lineGraph");
      if (depositChartElement) {
        const depositChart = new window.ApexCharts(depositChartElement, depositChartOptions);
        depositChart.render();
        window.depositChart = depositChart; // Store reference for potential updates
      }
    });
  }

  // CartManager handles all cart button clicks generically via data-cart-id and data-cart-type attributes
}
