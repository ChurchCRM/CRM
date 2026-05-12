/**
 * Telemetry consent card handler.
 * Wired into the admin-dashboard bundle; handles "Enable" / "No thanks" clicks
 * on the #telemetry-consent-card shown on first boot and after each upgrade.
 */

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-telemetry-consent");
  if (!btn) return;

  const enable = btn.getAttribute("data-enable") === "true";
  const root = (window.CRM && window.CRM.root) || "";

  fetch(`${root}/api/system/telemetry-consent`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ enable }),
  }).then(() => {
    const card = document.getElementById("telemetry-consent-card");
    if (card) card.remove();
  });
});
