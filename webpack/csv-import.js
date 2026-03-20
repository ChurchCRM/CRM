/**
 * CSV Import — drag-and-drop UI with Uppy XHR upload
 */

import Uppy from "@uppy/core";
import XHRUpload from "@uppy/xhr-upload";

$(document).ready(function () {
  const $dropzone = $("#dropzone");
  const $fileInput = $("#csvFile");
  const $fileInfo = $("#fileInfo");
  const $fileName = $("#fileName");
  const $fileSize = $("#fileSize");

  // --- Uppy instance (upload only, no Dashboard UI) ---
  const uppy = new Uppy({
    id: "csv-import",
    autoProceed: false,
    restrictions: { maxNumberOfFiles: 1, allowedFileTypes: [".csv", "text/csv"] },
  }).use(XHRUpload, {
    endpoint: window.CRM.root + "/admin/api/import/csv/upload",
    fieldName: "csvFile",
    withCredentials: true,
    getResponseData: (responseText) => {
      try {
        return JSON.parse(responseText);
      } catch {
        return { message: responseText };
      }
    },
  });

  uppy.on("upload-success", () => {
    setStatus("idle");
    window.CRM.notify(i18next.t("CSV uploaded successfully"), { type: "success", delay: 3000 });
  });

  uppy.on("upload-error", (_file, error) => {
    setStatus("error", error.message || i18next.t("Upload failed. Please check the file and try again."));
  });

  // --- Dropzone interaction ---
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
      $fileInput[0].files = files;
      setFile(files[0]);
    }
  });

  function setFile(file) {
    $fileName.text(file.name);
    $fileSize.text(formatSize(file.size));
    $fileInfo.removeClass("d-none");
    $dropzone.addClass("has-file");
  }

  // --- Form submit ---
  $("#csv-import-form").on("submit", function (e) {
    e.preventDefault();
    const file = $fileInput[0].files[0];

    if (!file) {
      window.CRM.notify(i18next.t("Please select a CSV file"), { type: "error", delay: 3000 });
      return;
    }
    if (!file.name.toLowerCase().endsWith(".csv")) {
      window.CRM.notify(i18next.t("Only .csv files are supported"), { type: "error", delay: 3000 });
      return;
    }

    uppy.cancelAll();
    uppy.addFile({ name: file.name, type: file.type || "text/csv", data: file });

    setStatus("running");
    uppy.upload();
  });
});

function formatSize(bytes) {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

function setStatus(status, errorMessage) {
  $("#statusCard").toggleClass("d-none", status === "idle");
  $("#statusRunning, #statusError").addClass("d-none");
  if (status === "running") $("#statusRunning").removeClass("d-none");
  if (status === "error") {
    if (errorMessage) $("#errorMessage").text(errorMessage);
    $("#statusError").removeClass("d-none");
  }
}
