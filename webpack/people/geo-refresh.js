/**
 * Shared "Refresh Coordinates" button handler.
 *
 * Looks for #refresh-coordinates-btn with data-family-id, then calls
 * POST /family/{id}/geocode. On success reloads the page so the map appears.
 *
 * Used by both family-view.js and person-view.js.
 */
import { fetchAPIJSON } from "../api-utils";

export function initRefreshCoordinatesBtn() {
  const refreshBtn = document.getElementById("refresh-coordinates-btn");
  if (!refreshBtn) return;

  const familyId = parseInt(refreshBtn.dataset.familyId || "0");
  if (familyId <= 0) return;

  const t = window.i18next ? i18next.t.bind(i18next) : (s) => s;

  refreshBtn.addEventListener("click", async function () {
    const originalText = this.innerHTML;

    try {
      this.disabled = true;
      this.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i>${t("Refreshing...")}`;

      const result = await fetchAPIJSON(`family/${familyId}/geocode`, {
        method: "POST",
      });

      if (result.success) {
        this.classList.remove("btn-outline-success");
        this.classList.add("btn-outline-primary");
        this.innerHTML = `<i class="fa-solid fa-check mr-1"></i>${t("Coordinates Updated")}`;
        setTimeout(() => location.reload(), 1500);
      } else {
        this.classList.remove("btn-outline-success");
        this.classList.add("btn-outline-danger");
        this.innerHTML = `<i class="fa-solid fa-exclamation-triangle mr-1"></i>${t("Failed to geocode")}`;
        this.disabled = false;
        setTimeout(() => {
          this.classList.remove("btn-outline-danger");
          this.classList.add("btn-outline-success");
          this.innerHTML = originalText;
        }, 3000);
      }
    } catch (error) {
      this.classList.remove("btn-outline-success");
      this.classList.add("btn-outline-danger");
      this.innerHTML = `<i class="fa-solid fa-network-wired"></i> ${t("Error")}`;
      this.disabled = false;
      console.error("Geocoding error:", error);
      setTimeout(() => {
        this.classList.remove("btn-outline-danger");
        this.classList.add("btn-outline-success");
        this.innerHTML = originalText;
      }, 3000);
    }
  });
}
