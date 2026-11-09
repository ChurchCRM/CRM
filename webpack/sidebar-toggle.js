const STORAGE_KEY = "churchcrm.desktopSidebarCollapsed";

function startDesktopSidebarToggle() {
  const button = document.getElementById("desktop-sidebar-toggle");
  const sidebar = document.getElementById("sidebar");
  if (!button || !sidebar) {
    return;
  }

  let collapsed = false;
  try {
    collapsed = window.localStorage.getItem(STORAGE_KEY) === "true";
  } catch {
    // Storage is optional.
  }

  const render = () => {
    document.body.classList.toggle("desktop-sidebar-collapsed", collapsed);
    button.setAttribute("aria-expanded", String(!collapsed));
    const label = collapsed ? button.dataset.expandLabel : button.dataset.collapseLabel;
    if (label) {
      button.setAttribute("aria-label", label);
      button.setAttribute("title", label);
    }
    window.dispatchEvent(new Event("resize"));
  };

  button.addEventListener("click", () => {
    collapsed = !collapsed;
    render();
    try {
      window.localStorage.setItem(STORAGE_KEY, String(collapsed));
    } catch {
      // The control still works for this page.
    }
  });

  render();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", startDesktopSidebarToggle, { once: true });
} else {
  startDesktopSidebarToggle();
}
