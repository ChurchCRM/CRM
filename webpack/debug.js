import { fetchAdminAPI } from "./api-utils";
import { notifyError } from "./common/notify";

document.addEventListener("change", async (event) => {
  const toggle = event.target;
  if (toggle.id !== "telemetry-page-usage") return;

  const checked = toggle.checked;
  toggle.disabled = true;
  try {
    await fetchAdminAPI("system/telemetry-consent", {
      method: "POST",
      body: JSON.stringify({ level: checked ? "full" : "default" }),
    });
    window.location.reload();
  } catch {
    toggle.checked = !checked;
    toggle.disabled = false;
    notifyError(window.i18next.t("Failed to save settings"));
  }
});
