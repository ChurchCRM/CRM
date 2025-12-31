/**
 * Photo utilities for Person and Family views
 * Handles lightbox display and photo deletion
 */

import { buildAPIUrl } from './api-utils';

/**
 * Show photo in a lightbox modal
 * @param entityType - 'person' or 'family'
 * @param entityId - The ID of the person or family
 */
export function showPhotoLightbox(entityType: string, entityId: number | string): void {
    // Get the actual image source from the rendered photo
    const photoImg = document.querySelector(
        `[data-image-entity-type="${entityType}"][data-image-entity-id="${entityId}"]`
    ) as HTMLImageElement;

    let imageSrc = "";
    if (photoImg && photoImg.src) {
        // Use the src from the photo element (already rendered by avatar-loader)
        imageSrc = photoImg.src;
    } else {
        // Fallback to the photo endpoint
        imageSrc = buildAPIUrl(`${entityType}/${entityId}/photo`);
    }

    // Create lightbox overlay
    const lightbox = document.createElement("div");
    lightbox.id = "photo-lightbox";
    lightbox.style.cssText =
        "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;z-index:9999;cursor:pointer;";

    const closeBtn = document.createElement("button");
    closeBtn.innerHTML = '<i class="fa-solid fa-times"></i>';
    closeBtn.style.cssText =
        "position:absolute;top:20px;right:20px;background:transparent;border:none;color:white;font-size:30px;cursor:pointer;z-index:10000;";

    const img = document.createElement("img");
    img.src = imageSrc;
    img.style.cssText =
        "max-width:90%;max-height:90%;object-fit:contain;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.5);";

    lightbox.appendChild(closeBtn);
    lightbox.appendChild(img);
    document.body.appendChild(lightbox);

    // Close on background click or close button (not on image)
    const closeLightbox = () => lightbox.remove();
    lightbox.addEventListener("click", function (e) {
        if (e.target === lightbox) closeLightbox();
    });
    closeBtn.addEventListener("click", closeLightbox);

    // Prevent image clicks from bubbling to lightbox
    img.addEventListener("click", function (e) {
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
    (window as any).CRM.APIRequest({
        method: "DELETE",
        path: `${entityType}/${entityId}/photo`,
    }).done(function () {
        location.reload();
    });
}
