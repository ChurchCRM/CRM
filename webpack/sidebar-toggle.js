/**
 * Desktop-only sidebar visibility. The responsive Tabler hamburger continues
 * to own tablet and phone navigation; only the desktop aside changes display.
 */
const STORAGE_KEY = "churchcrm.desktopSidebarCollapsed";

function startDesktopSidebarToggle() {
  const button = document.getElementById("desktop-sidebar-toggle");
  const sidebar = document.getElementById("sidebar");
  if (!button || !sidebar) return;

  let collapsed = false;
  try {
    collapsed = window.localStorage.getItem(STORAGE_KEY) === "true";
  } catch {
    // Storage is optional (privacy mode, blocked or quota exhausted).
  }

  const render = () => {
    document.body.classList.toggle("desktop-sidebar-collapsed", collapsed);
    button.setAttribute("aria-expanded", String(!collapsed));
    const label = collapsed ? button.dataset.expandLabel : button.dataset.collapseLabel;
    if (label) {
      button.setAttribute("aria-label", label);
      button.setAttribute("title", label);
    }
  };

  button.addEventListener("click", () => {
    collapsed = !collapsed;
    render();
    try {
      window.localStorage.setItem(STORAGE_KEY, String(collapsed));
    } catch {
      // The interactive control still works for this page even without storage.
    }
  });

  render();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", startDesktopSidebarToggle, { once: true });
} else {
  startDesktopSidebarToggle();
}
