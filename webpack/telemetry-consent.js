/** The notification-center toggle for the optional page-usage choice. Closing it means "no thanks". */

function save(item, level) {
  const root = window.CRM?.root || "";
  return fetch(`${root}/admin/api/system/telemetry-consent`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ level }),
  }).then((response) => {
    if (!response.ok) throw new Error("Telemetry setting was not saved");
    item.remove();
    const center = document.getElementById("notificationCenterMenuItem");
    const remaining = center ? center.querySelectorAll("[data-notification-id]").length : 0;
    if (remaining === 0) center?.remove();
    else document.getElementById("notificationCenterCount").textContent = remaining;
  });
}

document.addEventListener("change", (e) => {
  if (!e.target.matches("#telemetry-consent-toggle")) return;
  const toggle = e.target;
  toggle.disabled = true;
  save(toggle.closest("[data-notification-id]"), toggle.checked ? "full" : "default").catch(() => {
    toggle.checked = false;
    toggle.disabled = false;
  });
});

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-telemetry-decline");
  if (!btn) return;
  btn.disabled = true;
  save(btn.closest("[data-notification-id]"), "default").catch(() => {
    btn.disabled = false;
  });
});
