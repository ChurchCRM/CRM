/**
 * Admin → Feature Toggles: saves a switch or the Volunteer Management choice as it changes,
 * shows the result beside the row, then reloads so the sidebar follows the new state.
 */
import { fetchAdminAPI } from "./api-utils";

type ControlRow = { row: HTMLElement; badge: HTMLElement };

function controlRow(control: HTMLElement): ControlRow | null {
  const row = control.closest<HTMLElement>("tr");
  const badge = row?.querySelector<HTMLElement>(".status-badge");
  return row && badge ? { row, badge } : null;
}

function showStatus(badge: HTMLElement, text: string, tone: "info" | "success" | "danger"): void {
  badge.textContent = text;
  badge.style.display = "inline";
  badge.className = `status-badge badge bg-${tone}`;
}

function hideStatusLater(badge: HTMLElement): void {
  setTimeout(() => {
    badge.style.display = "none";
  }, 3000);
}

async function save(control: HTMLElement, path: string, body: Record<string, string>): Promise<boolean> {
  const target = controlRow(control);
  if (!target) {
    return false;
  }
  showStatus(target.badge, i18next.t("Saving..."), "info");
  try {
    await fetchAdminAPI(path, { method: "POST", body: JSON.stringify(body) });
  } catch {
    showStatus(target.badge, i18next.t("Error"), "danger");
    hideStatusLater(target.badge);
    return false;
  }
  showStatus(target.badge, i18next.t("Saved"), "success");
  setTimeout(() => window.location.reload(), 1500);
  return true;
}

function wire(): void {
  document.querySelectorAll<HTMLInputElement>(".feature-toggle").forEach((toggle) => {
    toggle.addEventListener("change", async () => {
      const saved = await save(toggle, "system/feature-toggles", {
        [toggle.dataset.setting ?? ""]: toggle.checked ? "1" : "0",
      });
      if (!saved) {
        toggle.checked = !toggle.checked;
      }
    });
  });

  document.querySelectorAll<HTMLSelectElement>(".feature-choice").forEach((select) => {
    let current = select.value;
    select.addEventListener("change", async () => {
      const saved = await save(select, `system/config/${encodeURIComponent(select.dataset.setting ?? "")}`, {
        value: select.value,
      });
      if (saved) {
        current = select.value;
      } else {
        select.value = current;
      }
    });
  });
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(wire);
} else {
  wire();
}
