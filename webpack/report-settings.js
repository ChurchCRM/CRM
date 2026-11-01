/**
 * Report Settings page: the shared photo uploader for the letterhead. The server
 * renders the letterhead state, so an upload or a removal just reloads the page.
 */
import { buildAdminAPIUrl } from "./api-utils";

const LETTERHEAD_API_PATH = "system/letterhead";

document.addEventListener("DOMContentLoaded", () => {
  const uploadBtn = document.getElementById("church-letterhead-upload-btn");
  if (!uploadBtn || typeof window._CRM_createPhotoUploader !== "function") {
    return;
  }

  window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
  window.CRM.photoUploader = window.CRM.createPhotoUploader({
    uploadUrl: buildAdminAPIUrl(LETTERHEAD_API_PATH),
    maxFileSize: window.CRM.maxUploadSizeBytes,
    aspectRatio: "free",
    photoWidth: 2400,
    photoHeight: 600,
    webcam: false,
    title: window.i18next ? i18next.t("Letterhead") : "Letterhead",
    onComplete: () => window.location.reload(),
  });
  uploadBtn.addEventListener("click", () => window.CRM.photoUploader.show());

  document.getElementById("church-letterhead-remove-btn")?.addEventListener("click", () => {
    window.CRM.AdminAPIRequest({ method: "DELETE", path: LETTERHEAD_API_PATH }).done(() => window.location.reload());
  });
});
