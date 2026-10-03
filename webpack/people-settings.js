/**
 * People Settings page only. The shared settings panel owns country and state.
 * This binds the "Copy from Church Info" button on the new-record defaults heading.
 */

import { fetchAdminAPIJSON } from "./api-utils";

document.addEventListener("DOMContentLoaded", () => {
  const panel = document.getElementById("peopleNewMembers");
  if (!panel) {
    return;
  }

  const bind = () => {
    const button = document.getElementById("copy-church-address");
    if (!button || button.dataset.bound) {
      return Boolean(button);
    }
    button.dataset.bound = "1";
    button.addEventListener("click", () => copyChurchAddress(panel));
    return true;
  };

  if (!bind()) {
    const observer = new MutationObserver(() => {
      if (bind()) {
        observer.disconnect();
      }
    });
    observer.observe(panel, { childList: true, subtree: true });
  }
});

function countryCode(select, value) {
  if (!value) {
    return "";
  }
  const upper = String(value).toUpperCase();
  for (const option of select.options) {
    if (option.value.toUpperCase() === upper || option.textContent === value) {
      return option.value;
    }
  }
  return "";
}

function t(key) {
  return window.i18next ? i18next.t(key) : key;
}

async function copyChurchAddress(panel) {
  const keys = ["sChurchCountry", "sChurchState", "sChurchCity", "sChurchZip"];
  try {
    const values = await Promise.all(
      keys.map(async (key) => (await fetchAdminAPIJSON(`system/config/${key}`)).value || ""),
    );
    const country = panel.querySelector("[name='sDefaultCountry']");
    const code = countryCode(country, values[0]);
    if (!code && values[0]) {
      window.CRM.notify(t("Could not match the church country."), { type: "warning", delay: 4000 });
      return;
    }
    // The panel's country handler reads this so the state list keeps the church state.
    country.dataset.keepState = values[1];
    country.value = code;
    country.dispatchEvent(new Event("change", { bubbles: true }));

    ["sDefaultCity", "sDefaultZip"].forEach((name, index) => {
      const input = panel.querySelector(`[name='${name}']`);
      input.value = values[index + 2];
      input.dispatchEvent(new Event("change", { bubbles: true }));
    });
  } catch {
    window.CRM.notify(t("Could not copy the church address."), { type: "error", delay: 4000 });
  }
}
