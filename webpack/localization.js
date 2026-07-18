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

/** Named currency presets: symbol, position (before|after), thousands sep, decimal sep. */
const CURRENCY_PRESETS = [
  { slug: "usd", label: "US Dollar ($)", symbol: "$", position: "before", thousand: ",", decimal: "." },
  { slug: "eur", label: "Euro (\u20ac)", symbol: "\u20ac", position: "after", thousand: ".", decimal: "," },
  { slug: "gbp", label: "British Pound (\u00a3)", symbol: "\u00a3", position: "before", thousand: ",", decimal: "." },
  { slug: "chf", label: "Swiss Franc (CHF)", symbol: "CHF", position: "before", thousand: "'", decimal: "." },
  { slug: "brl", label: "Brazilian Real (R$)", symbol: "R$", position: "before", thousand: ".", decimal: "," },
  { slug: "inr", label: "Indian Rupee (\u20b9)", symbol: "\u20b9", position: "before", thousand: ",", decimal: "." },
];

/**
 * Format a sample monetary amount (1,234.56) using the configured symbol,
 * position, and separator characters for the live preview on the
 * Currency & Finance Formats section of the Localization page.
 */
function formatCurrencySample(symbol, position, thousand, decimal) {
  const intPart = "1234";
  const fracPart = "56";
  const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousand || ",");
  const formatted = grouped + (decimal || ".") + fracPart;
  const sym = symbol || "$";
  return position === "after" ? `${formatted}\u00A0${sym}` : `${sym}\u00A0${formatted}`;
}

/**
 * Wire up the live preview inside the Currency & Finance Formats card.
 * Reads the four inputs and renders a formatted sample value (1,234.56).
 */
function initCurrencyPreview() {
  const symbolInput = document.getElementById("sCurrencySymbol");
  const positionSelect = document.getElementById("sCurrencyPosition");
  const thousandsInput = document.getElementById("sThousandsSeparator");
  const decimalInput = document.getElementById("sDecimalSeparator");
  const previewEl = document.getElementById("currency-format-preview");

  if (!symbolInput || !positionSelect || !thousandsInput || !decimalInput || !previewEl) {
    return;
  }

  function render() {
    const symbol = symbolInput.value.trim() || "$";
    const position = positionSelect.value || "before";
    const thousand = thousandsInput.value.slice(0, 1) || ",";
    const decimal = decimalInput.value.slice(0, 1) || ".";
    previewEl.textContent = formatCurrencySample(symbol, position, thousand, decimal);
  }

  for (const el of [symbolInput, positionSelect, thousandsInput, decimalInput]) {
    el.addEventListener("input", render);
    el.addEventListener("change", render);
  }
  render();
}

/**
 * Render the six currency quick-preset buttons into #currency-presets.
 * Clicking a button fills the four currency inputs and triggers the live
 * preview to update. Does NOT submit the form.
 */
function initCurrencyPresets() {
  const container = document.getElementById("currency-presets");
  const symbolInput = document.getElementById("sCurrencySymbol");
  const positionSelect = document.getElementById("sCurrencyPosition");
  const thousandsInput = document.getElementById("sThousandsSeparator");
  const decimalInput = document.getElementById("sDecimalSeparator");

  if (!container || !symbolInput || !positionSelect || !thousandsInput || !decimalInput) {
    return;
  }

  for (const preset of CURRENCY_PRESETS) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-outline-secondary btn-sm currency-preset-btn";
    btn.dataset.preset = preset.slug;
    btn.setAttribute("aria-label", `Apply ${preset.label} preset`);
    btn.textContent = preset.label;

    btn.addEventListener("click", () => {
      symbolInput.value = preset.symbol;
      positionSelect.value = preset.position;
      thousandsInput.value = preset.thousand;
      decimalInput.value = preset.decimal;

      // Notify live preview + any dirty-tracking listeners
      for (const el of [symbolInput, positionSelect, thousandsInput, decimalInput]) {
        el.dispatchEvent(new Event("input", { bubbles: true }));
        el.dispatchEvent(new Event("change", { bubbles: true }));
      }
    });

    container.appendChild(btn);
  }
}
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
  initCurrencyPreview();
  initCurrencyPresets();

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
          placeholder: "Search or select...",
        });
      }
    });
  }
});
