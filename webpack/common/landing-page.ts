import { fetchAPIJSON } from "../api-utils";
import { notifyError, notifySuccess } from "./notify";

document.addEventListener("click", async (event) => {
  const button = (event.target as Element).closest<HTMLButtonElement>("#setLandingPage");
  if (!button || button.disabled) {
    return;
  }
  try {
    await fetchAPIJSON(`user/${window.CRM?.userId}/setting/ui.landing`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ value: button.dataset.landing }),
    });
    const next = document.querySelector("#dashboardSwitch .nav-link.active")?.getAttribute("href");
    const previous = document.querySelector("#sidebar .navbar-brand")?.getAttribute("href");
    if (next && previous) {
      document
        .querySelectorAll("#sidebar .navbar-brand, #sidebar .navbar-nav > .nav-item > a.nav-link, .breadcrumb a")
        .forEach((link) => {
          if (link.getAttribute("href") === previous) {
            link.setAttribute("href", next);
          }
        });
    }
    button.disabled = true;
    const label = button.querySelector(".landing-label");
    if (label) {
      label.textContent = i18next.t("Your main dashboard");
    }
    notifySuccess(i18next.t("This dashboard is now your main dashboard."));
  } catch {
    notifyError(i18next.t("Could not save your main dashboard."));
  }
});
