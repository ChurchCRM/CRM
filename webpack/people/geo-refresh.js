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
    const btn = this;
    const originalText = btn.innerHTML;

    try {
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i>${t("Refreshing...")}`;

      const result = await fetchAPIJSON(`family/${familyId}/geocode`, {
        method: "POST",
      });

      if (result.success) {
        btn.classList.remove("btn-outline-success");
        btn.classList.add("btn-outline-primary");
        btn.innerHTML = `<i class="fa-solid fa-check mr-1"></i>${t("Coordinates Updated")}`;
        setTimeout(() => location.reload(), 1500);
      } else {
        btn.classList.remove("btn-outline-success");
        btn.classList.add("btn-outline-danger");
        btn.innerHTML = `<i class="fa-solid fa-exclamation-triangle mr-1"></i>${t("Failed to geocode")}`;
        btn.disabled = false;
        setTimeout(() => {
          btn.classList.remove("btn-outline-danger");
          btn.classList.add("btn-outline-success");
          btn.innerHTML = originalText;
        }, 3000);
      }
    } catch (error) {
      btn.classList.remove("btn-outline-success");
      btn.classList.add("btn-outline-danger");
      btn.innerHTML = `<i class="fa-solid fa-network-wired"></i> ${t("Error")}`;
      btn.disabled = false;
      console.error("Geocoding error:", error);
      setTimeout(() => {
        btn.classList.remove("btn-outline-danger");
        btn.classList.add("btn-outline-success");
        btn.innerHTML = originalText;
      }, 3000);
    }
  });
}
