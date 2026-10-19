(() => {
  "use strict";
  const prefix = "/shepherd";
  if (window.parent === window) {
    if (!window.location.pathname.startsWith(prefix)) return;
    const route = window.location.pathname.slice(prefix.length) || "/";
    window.location.replace("/shepherd.html#" + route + window.location.search + window.location.hash);
    return;
  }

  const parentOrigin = window.location.origin;
  const postRoute = () => {
    const path = window.location.pathname.startsWith(prefix)
      ? window.location.pathname.slice(prefix.length) || "/"
      : "/";
    window.parent.postMessage(
      { type: "shepherd:route", route: path + window.location.search + window.location.hash },
      parentOrigin,
    );
  };

  for (const method of ["pushState", "replaceState"]) {
    const original = history[method];
    history[method] = function (...args) {
      const result = original.apply(this, args);
      queueMicrotask(postRoute);
      return result;
    };
  }
  window.addEventListener("popstate", postRoute);
  window.addEventListener("hashchange", postRoute);
  window.addEventListener("load", postRoute, { once: true });

  const handleLink = (event) => {
    if (
      event.defaultPrevented ||
      (event.type === "click" && event.button !== 0) ||
      (event.type === "auxclick" && event.button !== 1)
    )
      return;
    const link = event.target.closest("a[href]");
    if (!link || link.hasAttribute("download") || link.dataset.shepherdBypassLeave === "true") return;
    const raw = link.getAttribute("href");
    if (!raw || raw.startsWith("#")) return;
    let url;
    try {
      url = new URL(link.href, window.location.href);
    } catch {
      return;
    }
    if (!["http:", "https:", "mailto:", "tel:"].includes(url.protocol)) return;
    const isInternal =
      url.origin === window.location.origin && (url.pathname === prefix || url.pathname.startsWith(prefix + "/"));
    if (isInternal) return;
    event.preventDefault();
    window.parent.postMessage(
      {
        type: "shepherd:leave",
        url: url.href,
        newTab: event.button === 1 || event.ctrlKey || event.metaKey || event.shiftKey || link.target === "_blank",
      },
      parentOrigin,
    );
  };
  document.addEventListener("click", handleLink, true);
  document.addEventListener("auxclick", handleLink, true);
})();
