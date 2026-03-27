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
  // Helper to generate Tabler simple avatar with initials
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

    const dataAttr = type === "person" ? `data-person-id="${id}"` : `data-family-id="${id}"`;
    const viewClass = type === "person" ? "view-person-photo" : "view-family-photo";

    return `<span class="avatar avatar-sm rounded-circle ${viewClass}" ${dataAttr} style="background-color: ${color}; cursor: pointer;" title="${i18next.t("View Photo")}"><span class="avatar-title fs-6 fw-bold">${initials}</span></span>`;
  }

  let dataTableDashboardDefaults = {
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
  let actionFamilyColumn = {
    width: "15%",
    sortable: false,
    title: i18next.t("Action"),
    data: "FamilyId",
    className: "no-export",
    render: function (data, type, row) {
      return window.CRM.renderFamilyActionMenu(row.FamilyId, row.Name);
    },
    searchable: false,
  };

  let dataTableFamilyColumns = [
    {
      width: "35%",
      title: i18next.t("Name"),
      data: "Name",
      render: function (data, type, row) {
        // Show photo if available, otherwise show Tabler avatar with initials
        var photoIcon = "";
        if (row.HasPhoto) {
          photoIcon =
            '<img class="avatar avatar-sm rounded-circle" data-image-entity-type="family" data-image-entity-id="' +
            row.FamilyId +
            '" alt="" />';
        } else {
          photoIcon = generateTablerAvatar(row.Name, row.FamilyId, "family");
        }
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
      render: function (data, type, row) {
        if (!data) return '<span class="text-muted">—</span>';
        // Extract city and state from address (last parts before country)
        let parts = data.split(",").map(function (s) {
          return s.trim();
        });
        if (parts.length >= 2) {
          // Try to get city and state (usually 2nd and 3rd from end, before country)
          let cityState = parts.slice(-3, -1).join(", ");
          if (cityState) {
            return '<span title="' + data + '">' + cityState + "</span>";
          }
        }
        return '<span title="' + data + '">' + data.substring(0, 30) + (data.length > 30 ? "..." : "") + "</span>";
      },
    },
  ];

  let latestFamilyColumns = dataTableFamilyColumns.slice();
  latestFamilyColumns.push({
    width: "20%",
    title: i18next.t("Created"),
    data: "Created",
    render: function (data) {
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
  let latestFamiliesTable = $("#latestFamiliesDashboardItem").DataTable(dataTableConfig);
  latestFamiliesTable.on("draw", function () {
    syncCartButtons();
  });

  let updatedFamilyColumns = dataTableFamilyColumns.slice();
  updatedFamilyColumns.push({
    width: "20%",
    title: i18next.t("Updated"),
    data: "LastEdited",
    render: function (data) {
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
  let updatedFamiliesTable = $("#updatedFamiliesDashboardItem").DataTable(dataTableConfig);
  updatedFamiliesTable.on("draw", function () {
    syncCartButtons();
  });

  dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/persons/birthday",
      dataSrc: function (json) {
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
        render: function (data, type, row) {
          return "";
        },
      },
      {
        width: "60%",
        title: i18next.t("Name"),
        data: "FirstName",
        render: function (data, type, row) {
          var ageText = row.Age ? ' <small class="text-muted">(' + row.Age + ")</small>" : "";
          // Show photo if available, otherwise show Tabler avatar with initials
          var photoIcon = "";
          if (row.HasPhoto) {
            photoIcon =
              '<img class="avatar avatar-sm rounded-circle" data-image-entity-type="person" data-image-entity-id="' +
              row.PersonId +
              '" alt="" />';
          } else {
            photoIcon = generateTablerAvatar(row.FormattedName, row.PersonId, "person");
          }
          photoIcon += " ";
          return (
            '<div class="d-flex align-items-center gap-3">' +
            photoIcon +
            ' <div><a href="' +
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
        render: function (data, type, row) {
          if (row.Birthday === undefined) return "";
          let diff = row.DaysUntil;

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
  let birthdayPersonTable = $("#PersonBirthdayDashboardItem").DataTable(dataTableConfig);
  birthdayPersonTable.on("draw", function () {
    syncCartButtons();
    // Refresh image loader for dynamically added photos
    if (window.CRM && window.CRM.peopleImageLoader) {
      window.CRM.peopleImageLoader.refresh();
    }
  });

  dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/families/anniversaries",
      dataSrc: function (json) {
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
        render: function (data, type, row) {
          // Show photo if available, otherwise show Tabler avatar with initials
          var photoIcon = "";
          if (row.HasPhoto) {
            photoIcon =
              '<img class="avatar avatar-sm rounded-circle" data-image-entity-type="family" data-image-entity-id="' +
              row.FamilyId +
              '" alt="" />';
          } else {
            photoIcon = generateTablerAvatar(data, row.FamilyId, "family");
          }
          photoIcon += " ";
          return (
            '<div class="d-flex align-items-center gap-3">' +
            photoIcon +
            ' <a href="' +
            window.CRM.root +
            "/v2/family/" +
            row.FamilyId +
            '"><strong>' +
            data +
            "</strong></a></div>"
          );
        },
      },
      {
        width: "50%",
        title: i18next.t("Anniversary"),
        data: "WeddingDate",
        render: function (data, type, row) {
          if (!data) return "";
          let weddingDate = moment(data, ["MMMM D, YYYY", "MMMM D", "MM-DD-YYYY"]);
          let thisYear = moment().year();
          let anniversaryThisYear = weddingDate.clone().year(thisYear);
          let today = moment().startOf("day");
          let diff = anniversaryThisYear.diff(today, "days");
          let years = thisYear - weddingDate.year();

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
  let anniversaryFamiliesTable = $("#FamiliesWithAnniversariesDashboardItem").DataTable(dataTableConfig);
  anniversaryFamiliesTable.on("draw", function () {
    syncCartButtons();
    if (window.CRM && window.CRM.peopleImageLoader) {
      window.CRM.peopleImageLoader.refresh();
    }
  });

  // Define action column for persons and base columns without action
  let actionPersonColumn = {
    width: "15%",
    sortable: false,
    title: i18next.t("Action"),
    data: "PersonId",
    className: "no-export",
    render: function (data, type, row) {
      return window.CRM.renderPersonActionMenu(row.PersonId, row.FirstName + " " + row.LastName, {
        familyId: row.FamilyId || null,
      });
    },
    searchable: false,
  };

  let dataTablePersonColumns = [
    {
      width: "25%",
      title: i18next.t("Name"),
      data: "FirstName",
      render: function (data, type, row) {
        // Show photo if available, otherwise show Tabler avatar with initials
        var photoIcon = "";
        if (row.HasPhoto) {
          photoIcon =
            '<img class="avatar avatar-sm rounded-circle" data-image-entity-type="person" data-image-entity-id="' +
            row.PersonId +
            '" alt="" />';
        } else {
          photoIcon = generateTablerAvatar(row.FirstName + " " + row.LastName, row.PersonId, "person");
        }
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
      render: function (data, type, row) {
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

  let updatedPersonColumns = dataTablePersonColumns.slice();
  updatedPersonColumns.push({
    width: "20%",
    title: i18next.t("Updated"),
    data: "LastEdited",
    render: function (data) {
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
  let updatedPersonTable = $("#updatedPersonDashboardItem").DataTable(dataTableConfig);
  updatedPersonTable.on("draw", function () {
    syncCartButtons();
    // No need to refresh image loader; inline photos have been removed
  });

  let latestPersonColumns = dataTablePersonColumns.slice();
  latestPersonColumns.push({
    width: "20%",
    title: i18next.t("Created"),
    data: "Created",
    render: function (data) {
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
  let latestPersonTable = $("#latestPersonDashboardItem").DataTable(dataTableConfig);
  latestPersonTable.on("draw", function () {
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
      ]).then(function (responses) {
        let cartData = responses[0];
        let familiesData = responses[1];

        let peopleInCart = cartData.PeopleCart || [];
        let familiesInCart = familiesData.familiesInCart || [];
        let groupsInCart = cartData.GroupCart || [];

        window.CRM.cartManager.syncButtonStates(peopleInCart, familiesInCart, groupsInCart);
      });
    }
  }

  function buildRenderEmail(email) {
    if (email) {
      return "<a href='mailto:" + email + "''>" + email + "</a>";
    }
    return "";
  }

  if ($("#depositChartRow").is(":visible")) {
    window.CRM.APIRequest({
      method: "GET",
      path: "deposits/dashboard",
    }).done(function (data) {
      let lineDataRaw = data;

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

      let labels = [];
      let values = [];
      $.each(lineDataRaw, function (i, val) {
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
        colors: ["#3366ff"],
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
