/**
 * CSV Import — drag-and-drop upload + auto-detected column mapping
 */

import Uppy from "@uppy/core";
import XHRUpload from "@uppy/xhr-upload";

$(document).ready(function () {
  const $dropzone = $("#dropzone");
  const $fileInput = $("#csvFile");
  const $fileInfo = $("#fileInfo");
  const $fileName = $("#fileName");
  const $fileSize = $("#fileSize");

  // --- Uppy ---
  const uppy = new Uppy({
    id: "csv-import",
    autoProceed: false,
    restrictions: { maxNumberOfFiles: 1, allowedFileTypes: [".csv", "text/csv"] },
  }).use(XHRUpload, {
    endpoint: window.CRM.root + "/admin/api/import/csv/upload",
    fieldName: "csvFile",
    withCredentials: true,
  });

  uppy.on("upload-success", (_file, response) => {
    // Uppy v5 auto-parses JSON responses into response.body
    const data = response.body;
    if (!data || !Array.isArray(data.headers) || data.headers.length === 0) {
      setStatus("error", i18next.t("Server returned an unexpected response. Please try again."));
      return;
    }
    setStatus("idle");
    showMappingStep(data.token, data.headers, data.mappings, data.fields, data.sample);
  });

  uppy.on("upload-error", (_file, error, response) => {
    const msg =
      response?.body?.message || error.message || i18next.t("Upload failed. Please check the file and try again.");
    setStatus("error", msg);
  });

  // --- Dropzone ---
  $dropzone.on("click", function (e) {
    if (e.target !== $fileInput[0]) $fileInput[0].click();
  });
  $fileInput.on("click", (e) => e.stopPropagation());
  $fileInput.on("change", function () {
    if (this.files[0]) setFile(this.files[0]);
  });

  $dropzone.on("dragover dragenter", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass("dragover");
  });
  $dropzone.on("dragleave dragend", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass("dragover");
  });
  $dropzone.on("drop", function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass("dragover");
    const files = e.originalEvent.dataTransfer.files;
    if (files.length > 0) {
      const dt = new DataTransfer();
      dt.items.add(files[0]);
      $fileInput[0].files = dt.files;
      setFile(files[0]);
    }
  });

  function setFile(file) {
    $fileName.text(file.name);
    $fileSize.text(formatSize(file.size));
    $fileInfo.removeClass("d-none");
    $dropzone.addClass("has-file");
  }

  // --- Submit ---
  $("#csv-import-form").on("submit", function (e) {
    e.preventDefault();
    const file = $fileInput[0].files[0];
    if (!file) {
      window.CRM.notify(i18next.t("Please select a CSV file"), { type: "error", delay: 3000 });
      return;
    }
    uppy.cancelAll();
    uppy.addFile({ name: file.name, type: file.type || "text/csv", data: file });
    setStatus("running");
    uppy.upload();
  });

  // --- Execute import ---
  $("#execute-import").on("click", function () {
    const token = $("#mapping-token").val();
    const mapping = {};
    $("#mapping-tbody .mapping-select").each(function () {
      const header = $(this).data("header");
      const field = $(this).val();
      if (field) mapping[header] = field;
    });

    if (Object.keys(mapping).length === 0) {
      window.CRM.notify(i18next.t("Please map at least one column before importing"), { type: "error", delay: 3000 });
      return;
    }

    const $btn = $(this)
      .prop("disabled", true)
      .html(`<span class="spinner-border spinner-border-sm mr-2"></span>${i18next.t("Importing...")}`);

    $.ajax({
      url: window.CRM.root + "/admin/api/import/csv/execute",
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({ token, mapping }),
    })
      .done(function (data) {
        $("#mapping-card").addClass("d-none");
        setStatus("idle");
        $("#summary-imported").text(data.imported);
        $("#summary-families").text(data.families);
        $("#summary-skipped").text(data.skipped ?? 0);
        $("#summary-card").removeClass("d-none");
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON?.message || i18next.t("Import failed. Please try again.");
        window.CRM.notify(msg, { type: "error", delay: 5000 });
        $btn.prop("disabled", false).html(`<i class="fa-solid fa-file-import mr-2"></i>${i18next.t("Import Data")}`);
      });
  });

  // --- Start Over ---
  function resetImport() {
    uppy.cancelAll();
    $fileInput.val("");
    $fileInfo.addClass("d-none");
    $dropzone.removeClass("has-file");
    $("#mapping-card, #summary-card").addClass("d-none");
    $("#upload-card").removeClass("d-none");
    setStatus("idle");
  }
  $("#restart-import").on("click", resetImport);
  $("#restart-import-summary").on("click", resetImport);
});

// --- Mapping step ---
function showMappingStep(token, headers, mappings, fields, sample) {
  $("#upload-card").addClass("d-none");
  $("#mapping-card").removeClass("d-none");

  const $tbody = $("#mapping-tbody").empty();

  headers.forEach((header) => {
    const mapped = mappings[header] || null;
    const sampleValue = sample ? (sample[header] ?? "") : "";
    const rowClass = mapped ? "table-success" : "table-warning";
    const badge = mapped
      ? `<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>${i18next.t("Auto-mapped")}</span>`
      : `<span class="badge badge-warning"><i class="fa-solid fa-triangle-exclamation mr-1"></i>${i18next.t("Unmapped")}</span>`;

    const $select = $('<select class="form-control form-control-sm mapping-select">').attr("data-header", header);
    $select.append($("<option>").val("").text(i18next.t("— Ignore —")));
    fields.forEach((f) => {
      $select.append($("<option>").val(f).text(f).prop("selected", f === mapped));
    });

    const $row = $(`<tr class="${rowClass}">`);
    $("<td>").append($("<code>").text(header)).appendTo($row);
    $("<td>").append($("<small>").addClass("text-muted").text(sampleValue)).appendTo($row);
    $("<td>").html(badge).appendTo($row);
    $("<td>").append($select).appendTo($row);

    $tbody.append($row);
  });

  // Manual override styling
  $("#mapping-tbody").off("change", ".mapping-select").on("change", ".mapping-select", function () {
    const $row = $(this).closest("tr");
    $row.removeClass("table-success table-warning table-secondary");
    const val = $(this).val();
    if (val) {
      $row.addClass("table-success");
      $row
        .find(".badge")
        .replaceWith(
          `<span class="badge badge-primary"><i class="fa-solid fa-pen mr-1"></i>${i18next.t("Manual")}</span>`,
        );
    } else {
      $row.addClass("table-secondary");
      $row.find(".badge").replaceWith(`<span class="badge badge-secondary">${i18next.t("Ignored")}</span>`);
    }
  });

  $("#mapping-token").val(token);
}

function formatSize(bytes) {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

function setStatus(status, errorMessage) {
  $("#status-card").toggleClass("d-none", status === "idle");
  $("#statusRunning, #statusError").addClass("d-none");
  if (status === "running") $("#statusRunning").removeClass("d-none");
  if (status === "error") {
    if (errorMessage) $("#errorMessage").text(errorMessage);
    $("#statusError").removeClass("d-none");
  }
}
