/**
 * Debug page JavaScript.
 * Handles the telemetry enable/disable toggle in the App environment tab.
 */

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-debug-telemetry-toggle");
  if (!btn) return;

  const enable = btn.getAttribute("data-enable") === "true";
  btn.disabled = true;

  const root = (window.CRM && window.CRM.root) || "";
  fetch(`${root}/api/system/telemetry-consent`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ enable }),
  })
    .then(() => {
      window.location.reload();
    })
    .catch(() => {
      btn.disabled = false;
    });
});
