const FOLDED_CLASS = "navbar-folded-hover";
const SETTING = "ui.sidebar.folded";

function startDesktopSidebarToggle() {
  const button = document.getElementById("desktop-sidebar-toggle");
  const sidebar = document.getElementById("sidebar");
  if (!button || !sidebar) {
    return;
  }

  for (const link of sidebar.querySelectorAll(".navbar-nav > .nav-item > .nav-link")) {
    const counters = [...link.querySelectorAll(".menu-counter")];
    if (!counters.length) continue;
    const updateIndicator = () => {
      link.classList.toggle(
        "sidebar-has-count",
        counters.some((counter) => Number(counter.textContent) > 0),
      );
    };
    updateIndicator();
    const observer = new MutationObserver(updateIndicator);
    for (const counter of counters) {
      observer.observe(counter, { childList: true, characterData: true, subtree: true });
    }
  }

  // Below xl the open menu pushes the page down; opening from the top keeps it in view instead of letting scroll anchoring carry it above the header.
  const stacked = window.matchMedia("(max-width: 1199.98px)");
  sidebar.addEventListener("show.bs.collapse", (event) => {
    if (event.target.id === "sidebar-menu" && stacked.matches) {
      window.scrollTo({ top: 0, behavior: "instant" });
    }
  });

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
