/**
 * Family Verification Page
 * Handles avatar display and form interactions
 */

import "./family-verify.css";

document.addEventListener("DOMContentLoaded", function () {
  // Handle avatar display - show/hide initials based on photo presence
  document.querySelectorAll(".avatar-placeholder").forEach(function (container) {
    const img = container.querySelector(".avatar-img");
    const initials = container.querySelector(".initials");

    if (img && img.src) {
      img.addEventListener("load", function () {
        // Image loaded, hide initials
        if (initials) {
          initials.style.display = "none";
        }
        img.style.display = "block";
      });

      img.addEventListener("error", function () {
        // Image failed, show initials
        img.style.display = "none";
        if (initials) {
          initials.style.display = "block";
        }
      });
    }
  });

  // Photo viewer click handlers
  document.addEventListener("click", function (e) {
    const photoElement = e.target.closest(".view-person-photo");
    if (photoElement) {
      const personId = photoElement.getAttribute("data-person-id");
      if (window.CRM && window.CRM.showPhotoLightbox) {
        window.CRM.showPhotoLightbox("person", personId);
      }
      e.stopPropagation();
      return;
    }

    const familyPhotoElement = e.target.closest(".view-family-photo");
    if (familyPhotoElement) {
      const familyId = familyPhotoElement.getAttribute("data-family-id");
      if (window.CRM && window.CRM.showPhotoLightbox) {
        window.CRM.showPhotoLightbox("family", familyId);
      }
      e.stopPropagation();
    }
  });
});
