/**
 * Rewrite leftover legacy logo URLs and follow data-bs-theme.
 *
 * Loaded on every page via skin-core.js → churchcrm.min.js.
 * window.CRM.root is stamped by Header.php / HeaderNotLoggedIn.php
 * after this module evaluates, so path resolution waits for DOMContentLoaded.
 */

const LEGACY_SIDEBAR = "CRM_50x50.png";
const LEGACY_FULL = "logo-churchcrm-350.jpg";

function rootPath(): string {
  return window.CRM?.root ?? "";
}

function isDark(): boolean {
  return document.documentElement.getAttribute("data-bs-theme") === "dark";
}

function symbolSrc(): string {
  return rootPath() + (isDark() ? "/Images/churchcrm-symbol-paper-blue.svg" : "/Images/churchcrm-symbol-ink-blue.svg");
}

function fullSrc(): string {
  return rootPath() + "/Images/churchcrm-logo-ink-blue.svg";
}

function rewrite(selector: string, src: string): void {
  document.querySelectorAll<HTMLImageElement>(`img[src*="${selector}"]`).forEach((img) => {
    img.src = src;
  });
}

function applyBrandLogos(): void {
  rewrite(LEGACY_SIDEBAR, symbolSrc());
  rewrite(LEGACY_FULL, fullSrc());
}

function schedule(): void {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", applyBrandLogos, { once: true });
  } else {
    applyBrandLogos();
  }
}

schedule();
document.addEventListener("CRM.theme.changed", applyBrandLogos);

const themeObserver = new MutationObserver(applyBrandLogos);
themeObserver.observe(document.documentElement, {
  attributes: true,
  attributeFilter: ["data-bs-theme"],
});
