import { confirmAction } from "./common/confirm-action";

window.CRM.onLocalesReady(() => {
  let currentLogContent = "";
  let currentFilter = "all";

  const dataTableConfig = {
    order: [[2, "desc"]],
  };
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  dataTableConfig.paging = false;
  dataTableConfig.info = false;
  dataTableConfig.searching = false;
  dataTableConfig.layout = { topEnd: "buttons" };
  dataTableConfig.columnDefs = [{ orderable: false, targets: 3 }];
  $("#logFilesTable").DataTable(dataTableConfig);

  $(document).on("click", ".view-log", function () {
    const fileName = $(this).data("log-name");
    loadLogFile(fileName);
  });

  $(document).on("click", ".download-log", function (e) {
    e.preventDefault();
    const fileName = $(this).data("log-name");
    const rootPath = window.CRM && window.CRM.root ? window.CRM.root : "/";
    const url =
      rootPath +
      (rootPath.endsWith("/") ? "" : "/") +
      "admin/api/system/logs/" +
      encodeURIComponent(fileName) +
      "/download";
    try {
      fetch(url, { credentials: "same-origin" })
        .then((resp) => {
          if (!resp.ok) {
            window.CRM.notify(i18next.t("Error downloading log file."), { type: "danger" });
            return null;
          }

          const cd = resp.headers.get("Content-Disposition") || resp.headers.get("content-disposition");
          if (!cd) {
            window.CRM.notify(i18next.t("Download returned without attachment headers."), { type: "warning" });
          }

          return resp.blob();
        })
        .then((blob) => {
          if (!blob) {
            return;
          }
          const tmpUrl = URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = tmpUrl;
          a.download = fileName;
          document.body.appendChild(a);
          a.click();
          a.remove();
          setTimeout(() => {
            URL.revokeObjectURL(tmpUrl);
          }, 1000);
        })
        .catch(() => {
          window.CRM.notify(i18next.t("Error downloading log file."), { type: "danger" });
        });
    } catch (_e) {
      window.CRM.notify(i18next.t("Error initiating download."), { type: "danger" });
    }
  });

  $(document).on("click", ".delete-log", function (e) {
    e.preventDefault();
    const fileName = $(this).data("log-name");
    deleteLogFile(fileName);
  });

  $("#deleteAllLogs").on("click", () => {
    deleteAllLogs();
  });

  $(".log-filter").on("click", function () {
    $(".log-filter").removeClass("active");
    $(this).addClass("active");
    currentFilter = $(this).data("level");
    applyFilter();
  });

  $("#logLinesLimit").on("change", () => {
    applyFilter();
  });

  function loadLogFile(fileName) {
    $("#logViewerModalLabel").text(i18next.t("Log File Viewer") + " - " + fileName);
    $("#logContent").hide();
    $("#logLoading").show();
    $("#logViewerModal").modal("show");

    window.CRM.AdminAPIRequest({
      path: "system/logs/" + encodeURIComponent(fileName),
      method: "GET",
    })
      .done((data) => {
        if (typeof data === "object" && data.success && Array.isArray(data.lines)) {
          currentLogContent = data.lines;
        } else if (typeof data === "string") {
          currentLogContent = data.split("\n");
        } else {
          currentLogContent = [];
        }
        applyFilter();
        $("#logLoading").hide();
        $("#logContent").show();
      })
      .fail(() => {
        $("#logContent code").text(i18next.t("Error loading log file."));
        $("#logLoading").hide();
        $("#logContent").show();
      });
  }

  function applyFilter() {
    const lines = Array.isArray(currentLogContent) ? currentLogContent : currentLogContent.split("\n");
    const limit = $("#logLinesLimit").val();
    let filteredLines = lines;

    if (currentFilter !== "all") {
      filteredLines = lines.filter((line) => line.includes("." + currentFilter));
    }

    if (limit !== "all") {
      filteredLines = filteredLines.slice(-parseInt(limit));
    }

    $("#logContent code").text(filteredLines.join("\n"));
  }

  function deleteLogFile(fileName) {
    confirmAction(
      i18next.t("Delete"),
      i18next.t("Are you sure you want to delete {{name}}?", { name: window.CRM.escapeHtml(fileName) }),
      () => {
        window.CRM.AdminAPIRequest({
          path: "system/logs/" + encodeURIComponent(fileName),
          method: "DELETE",
        })
          .done(() => {
            location.reload();
          })
          .fail(() => {
            window.CRM.notify(i18next.t("Error deleting log file."), { type: "danger" });
          });
      },
    );
  }

  function deleteAllLogs() {
    confirmAction(
      i18next.t("Delete All"),
      i18next.t("Are you sure you want to delete ALL log files? This action cannot be undone."),
      () => {
        window.CRM.AdminAPIRequest({
          path: "system/logs",
          method: "DELETE",
        })
          .done(() => {
            location.reload();
          })
          .fail(() => {
            window.CRM.notify(i18next.t("Error deleting log files."), { type: "danger" });
          });
      },
    );
  }

  const logLevelMap = {
    100: "DEBUG",
    200: "INFO",
    250: "NOTICE",
    300: "WARNING",
    400: "ERROR",
    500: "CRITICAL",
    550: "ALERT",
    600: "EMERGENCY",
  };

  $(document).ready(() => {
    window.CRM.settingsPanel.init({
      container: "#logSettings",
      title: i18next.t("Log Settings"),
      settings: [
        {
          name: "sLogLevel",
          type: "choice",
          label: i18next.t("Log Level"),
          tooltip: window.CRM.logSettings.tooltip,
          choices: window.CRM.logSettings.choices,
        },
      ],
      onSave: () => {
        window.CRM.notify(i18next.t("Log settings saved"), { type: "success" });
        $.ajax({
          url: window.CRM.path + "api/system/config/sLogLevel",
          type: "GET",
          success: (data) => {
            const levelValue = data.value || "200";
            const levelLabel = logLevelMap[levelValue] || "INFO";
            $("#currentLogLevelDisplay").text(levelLabel);
          },
        });
      },
    });
  });
});
