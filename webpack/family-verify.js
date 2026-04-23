/**
 * Family Verification Page
 * Handles avatar display, form interactions, and verify submission
 */

import "./family-verify.css";

document.addEventListener("DOMContentLoaded", () => {
  // Handle avatar display - show/hide initials based on photo presence
  document.querySelectorAll(".avatar-placeholder").forEach((container) => {
    const img = container.querySelector(".avatar-img");
    const initials = container.querySelector(".initials");

    if (img && img.src) {
      img.addEventListener("load", () => {
        if (initials) {
          initials.style.display = "none";
        }
        img.style.display = "block";
      });

      img.addEventListener("error", () => {
        img.style.display = "none";
        if (initials) {
          initials.style.display = "block";
        }
      });
    }
  });

  // Photo viewer click handlers
  document.addEventListener("click", (e) => {
    const photoElement = e.target.closest(".view-person-photo");
    if (photoElement) {
      const personId = photoElement.getAttribute("data-person-id");
      if (window.CRM && window.CRM.showPhotoLightbox) {
        window.CRM.showPhotoLightbox("person", personId);
      }
      e.preventDefault();
      e.stopPropagation();
      return;
    }

    const familyPhotoElement = e.target.closest(".view-family-photo");
    if (familyPhotoElement) {
      const familyId = familyPhotoElement.getAttribute("data-family-id");
      if (window.CRM && window.CRM.showPhotoLightbox) {
        window.CRM.showPhotoLightbox("family", familyId);
      }
      e.preventDefault();
      e.stopPropagation();
    }
  });

  // --- Verify form submission ---
  const verifyBtn = document.getElementById("onlineVerifyBtn");
  const cancelBtn = document.getElementById("onlineVerifyCancelBtn");
  const siteBtn = document.getElementById("onlineVerifySiteBtn");
  const collectSection = document.getElementById("confirm-modal-collect");
  const doneSection = document.getElementById("confirm-modal-done");
  const errorSection = document.getElementById("confirm-modal-error");

  if (verifyBtn && typeof window.token !== "undefined") {
    verifyBtn.addEventListener("click", () => {
      const verifyType = document.querySelector('input[name="verifyType"]:checked');
      const textarea = document.getElementById("confirm-info-data");

      let message = "";
      if (verifyType && verifyType.value === "no-change") {
        message = "No Changes";
      } else if (textarea && textarea.value.trim()) {
        message = textarea.value.trim();
      } else {
        message = "Changes requested (no details provided)";
      }

      // Determine the base URL for the external verify endpoint
      const root = window.CRM ? window.CRM.root : "";

      fetch(`${root}/external/verify/${window.token}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message }),
      })
        .then((resp) => {
          if (resp.ok) {
            // Show success
            if (collectSection) collectSection.classList.add("d-none");
            if (doneSection) doneSection.classList.remove("d-none");
            if (errorSection) errorSection.classList.add("d-none");
            if (verifyBtn) verifyBtn.classList.add("d-none");
            if (cancelBtn) cancelBtn.classList.add("d-none");
            if (siteBtn) siteBtn.classList.remove("d-none");
          } else {
            throw new Error("Verification failed");
          }
        })
        .catch(() => {
          // Show error
          if (collectSection) collectSection.classList.add("d-none");
          if (doneSection) doneSection.classList.add("d-none");
          if (errorSection) errorSection.classList.remove("d-none");
        });
    });
  }
});
