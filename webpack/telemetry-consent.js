/**
 * Telemetry consent card handler.
 * Handles "Enable (full)" / "Errors only" / "No thanks" clicks on the
 * #telemetry-consent-card shown on first boot and after each upgrade.
 */

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-telemetry-consent");
  if (!btn) return;

  const level = btn.getAttribute("data-level") || "none";
  const root = (window.CRM && window.CRM.root) || "";

  btn.disabled = true;
  fetch(`${root}/api/system/telemetry-consent`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ level }),
  })
    .then(() => {
      const card = document.getElementById("telemetry-consent-card");
      if (card) card.remove();
    })
    .catch(() => {
      btn.disabled = false;
    });
});
