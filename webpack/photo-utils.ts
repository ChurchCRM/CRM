/**
 * Photo utilities for Person and Family views
 * Handles lightbox display and photo deletion
 */

import { buildAPIUrl } from "./api-utils";

/**
 * Show photo in a lightbox modal
 * @param entityType - 'person' or 'family'
 * @param entityId - The ID of the person or family
 */
export function showPhotoLightbox(entityType: string, entityId: number | string): void {
  // Always use the photo API endpoint for consistent loading
  const imageSrc = buildAPIUrl(`${entityType}/${entityId}/photo`);

  // Create lightbox overlay
  const lightbox = document.createElement("div");
  lightbox.id = "photo-lightbox";
  lightbox.className = "photo-lightbox";

  const closeBtn = document.createElement("button");
  closeBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
  closeBtn.className = "photo-lightbox-close";

  const img = document.createElement("img");
  img.src = imageSrc;
  img.className = "photo-lightbox-image";

  lightbox.appendChild(closeBtn);
  lightbox.appendChild(img);
  document.body.appendChild(lightbox);

  // Close on background click or close button (not on image)
  const closeLightbox = () => lightbox.remove();
  lightbox.addEventListener("click", (e) => {
    if (e.target === lightbox) closeLightbox();
  });
  closeBtn.addEventListener("click", closeLightbox);

  // Prevent image clicks from bubbling to lightbox
  img.addEventListener("click", (e) => {
    e.stopPropagation();
  });

  // Close on escape key
  const escHandler = (e: KeyboardEvent) => {
    if (e.key === "Escape") {
      closeLightbox();
      document.removeEventListener("keydown", escHandler);
    }
  };
  document.addEventListener("keydown", escHandler);
}

/**
 * Delete a photo and reload the page
 * @param entityType - 'person' or 'family'
 * @param entityId - The ID of the person or family
 */
export function deletePhoto(entityType: string, entityId: number | string): void {
  window.CRM?.APIRequest?.({
    method: "DELETE",
    path: `${entityType}/${entityId}/photo`,
  }).done(() => {
    location.reload();
  });
}
