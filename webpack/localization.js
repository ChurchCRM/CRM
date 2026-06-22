/**
 * Localization & Formats Page
 *
 * Drives the Admin → Localization & Formats page:
 * - Language picker (locale dropdown + TomSelect)
 * - Time Zone (and any other .auto-tomselect dropdowns)
 * - Live date/time format previews + token cheat-sheet
 * - Location-friendly phone-format presets
 * - Consolidated Display Preview (date/time + phone examples)
 * - Selected-language preview: translation completeness % + system support
 */

import { initDateFormatPreviews, initFormatSummaryPreview, initPhonePresets } from "./date-format-preview";

/** Convert a 2-letter country code to its flag emoji (falls back to a globe). */
function flagEmoji(countryCode) {
  if (!countryCode || countryCode.length !== 2) {
    return "🌐";
  }
  const base = 0x1f1e6;
  const cc = countryCode.toUpperCase();
  return String.fromCodePoint(base + cc.charCodeAt(0) - 65, base + cc.charCodeAt(1) - 65);
}

/**
 * Render the "Selected Language" preview block (translation % + system support)
 * based on the current #sLanguage value. Re-renders on change.
 */
function initLocalePreview() {
  const langSelect = document.getElementById("sLanguage");
  const stats = window.CRM?.localeStats;
  if (!langSelect || !stats) {
    return;
  }

  const i18n = window.CRM.localePreviewI18n || {};
  const esc = window.CRM.escapeHtml || ((s) => s);
  const flagEl = document.getElementById("locale-preview-flag");
  const nameEl = document.getElementById("locale-preview-name");
  const codeEl = document.getElementById("locale-preview-code");
  const pctEl = document.getElementById("locale-preview-percent");
  const barEl = document.getElementById("locale-preview-bar");
  const transWrap = document.getElementById("locale-preview-translation");
  const transNote = document.getElementById("locale-preview-translation-note");
  const supportEl = document.getElementById("locale-preview-support");

  function render() {
    const code = langSelect.value;
    const s = stats[code];

    if (!s) {
      flagEl.textContent = "🌐";
      nameEl.textContent = "—";
      codeEl.textContent = code || "";
      transWrap.style.display = "none";
      supportEl.innerHTML = "";
      return;
    }

    flagEl.textContent = flagEmoji(s.flag);
    nameEl.textContent = s.nativeName || s.name;
    codeEl.textContent = code;

    if (s.showPercentage) {
      transWrap.style.display = "";
      const pct = Number(s.percentage) || 0;
      pctEl.textContent = `${pct}%`;
      barEl.style.width = `${pct}%`;
      barEl.className = `progress-bar ${pct >= 90 ? "bg-success" : pct >= 50 ? "bg-warning" : "bg-danger"}`;
      transNote.textContent = pct >= 100 ? i18n.fullyTranslated || "" : (i18n.translated || "").replace("%d", pct);
    } else {
      transWrap.style.display = "none";
    }

    if (!window.CRM.localeSystemCheckEnabled || s.systemAvailable === null) {
      supportEl.innerHTML = `<span class="text-body-secondary"><i class="fa-solid fa-circle-question me-1"></i>${esc(i18n.checkUnavailable || "")}</span>`;
    } else if (s.systemAvailable) {
      supportEl.innerHTML = `<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>${esc(i18n.supported || "")}</span>`;
    } else {
      supportEl.innerHTML = `<span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>${esc(i18n.notSupported || "")}</span>`;
    }
  }

  langSelect.addEventListener("change", render);
  return render;
}

document.addEventListener("DOMContentLoaded", () => {
  // ── Date format live previews + phone presets + Display Preview summary ───
  initDateFormatPreviews();
  initPhonePresets();
  initFormatSummaryPreview();

  const renderLocalePreview = initLocalePreview();

  // ── Language picker ───────────────────────────────────────────────────────
  const langSelect = document.getElementById("sLanguage");
  if (langSelect && window.CRM?.populateLocaleDropdown) {
    const selected = langSelect.dataset.selectedLocale || "";
    window.CRM.populateLocaleDropdown(langSelect, selected)
      .then(() => {
        if (window.TomSelect && !langSelect.tomselect) {
          new window.TomSelect(langSelect, { allowEmptyOption: false, dropdownParent: "body" });
        }
        // Locale dropdown is now populated — render the preview for the selection.
        if (renderLocalePreview) {
          renderLocalePreview();
        }
      })
      .catch((e) => console.error("Failed to load language options:", e));
  }

  // ── Time Zone + any other auto-tomselect dropdowns ───────────────────────
  if (window.TomSelect) {
    document.querySelectorAll(".auto-tomselect").forEach((el) => {
      if (!el.tomselect) {
        new window.TomSelect(el, {
          allowEmptyOption: true,
          placeholder: window.i18next ? window.i18next.t("Search or select...") : "Search or select...",
        });
      }
    });
  }
});
