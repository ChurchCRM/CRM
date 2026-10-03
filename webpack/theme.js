/**
 * Blocking theme controller. Loaded from Header.php before the skin CSS so
 * "auto" can set data-bs-theme before first paint. Explicit "dark" is also
 * stamped on <html> by Header.php. user.js calls window.CRM.theme.setMode.
 */
(() => {
  const root = document.documentElement;
  window.CRM = window.CRM || {};

  const mql = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
  let listening = false;

  function applyDark() {
    root.setAttribute("data-bs-theme", "dark");
  }

  function applyLight() {
    root.removeAttribute("data-bs-theme");
  }

  function onChange(e) {
    if (e.matches) {
      applyDark();
    } else {
      applyLight();
    }
  }

  window.CRM.theme = {
    setMode(mode) {
      if (mode === "auto") {
        onChange({ matches: mql ? mql.matches : false });
        if (mql && !listening) {
          mql.addEventListener("change", onChange);
          listening = true;
        }
        return;
      }
      if (listening && mql) {
        mql.removeEventListener("change", onChange);
        listening = false;
      }
      if (mode === "dark") {
        applyDark();
      } else {
        applyLight();
      }
    },
  };

  const mode = root.getAttribute("data-theme-mode");
  if (mode) {
    window.CRM.theme.setMode(mode);
  }
})();
