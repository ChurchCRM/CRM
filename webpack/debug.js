/**
 * Debug page JavaScript.
 * Handles the telemetry level selector buttons in the App environment tab.
 */

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-debug-telemetry-toggle");
  if (!btn) return;

  const level = btn.getAttribute("data-level") || "none";
  btn.disabled = true;

  const root = window.CRM?.root || "";
  fetch(`${root}/api/system/telemetry-consent`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ level }),
  })
    .then(() => {
      window.location.reload();
    })
    .catch(() => {
      btn.disabled = false;
    });
});
