/** Records the optional page-usage choice on the admin dashboard. */

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-telemetry-consent");
  if (!btn) return;

  const level = btn.getAttribute("data-level") || "default";
  const root = window.CRM?.root || "";

  btn.disabled = true;
  fetch(`${root}/admin/api/system/telemetry-consent`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ level }),
  })
    .then((response) => {
      if (!response.ok) throw new Error("Telemetry setting was not saved");
      const card = document.getElementById("telemetry-consent-card");
      if (card) card.remove();
    })
    .catch(() => {
      btn.disabled = false;
    });
});
