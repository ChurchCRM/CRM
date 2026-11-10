const FOLDED_CLASS = "navbar-folded-hover";
const SETTING = "ui.sidebar.folded";

function startDesktopSidebarToggle() {
  const button = document.getElementById("desktop-sidebar-toggle");
  const sidebar = document.getElementById("sidebar");
  if (!button || !sidebar) {
    return;
  }

  let folded = sidebar.classList.contains(FOLDED_CLASS);

  const render = () => {
    sidebar.classList.toggle(FOLDED_CLASS, folded);
    button.setAttribute("aria-expanded", String(!folded));
    const label = folded ? button.dataset.expandLabel : button.dataset.collapseLabel;
    if (label) {
      button.setAttribute("aria-label", label);
      button.setAttribute("title", label);
    }
    window.dispatchEvent(new Event("resize"));
  };

  button.addEventListener("click", () => {
    folded = !folded;
    render();
    window.CRM?.APIRequest?.({
      method: "POST",
      path: `user/${window.CRM.userId}/setting/${SETTING}`,
      dataType: "json",
      data: JSON.stringify({ value: folded ? "1" : "0" }),
    });
  });

  render();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", startDesktopSidebarToggle, { once: true });
} else {
  startDesktopSidebarToggle();
}
