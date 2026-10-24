/**
 * The Admin Dashboard notice that asks to turn member sign-in on after the
 * upgrade removed the self-service accounts (member sign-in design §12).
 */

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-dismiss-former-portal-accounts");
  if (!btn) return;

  const notice = document.getElementById("former-portal-accounts-notice");
  const root = window.CRM?.root || "";

  btn.disabled = true;
  fetch(`${root}/admin/api/member-portal/former-accounts-notice/dismiss`, {
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
