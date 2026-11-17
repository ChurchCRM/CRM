/**
 * The Admin Dashboard notice that Volunteer Opportunities are being retired (#10606).
 * Dismissing it hides it until the next upgrade.
 */

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-dismiss-volunteer-v1-notice");
  if (!btn) return;

  const notice = document.getElementById("volunteer-v1-notice");
  const root = window.CRM?.root || "";

  btn.disabled = true;
  fetch(`${root}/admin/api/volunteer-conversion/notice/dismiss`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": notice?.dataset.csrfToken ?? "" },
    body: "{}",
  })
    .then((response) => {
      if (!response.ok) throw new Error(String(response.status));
      notice?.remove();
    })
    .catch(() => {
      btn.disabled = false;
      window.CRM?.notify?.(window.i18next ? window.i18next.t("Error") : "Error", { type: "danger" });
    });
});
