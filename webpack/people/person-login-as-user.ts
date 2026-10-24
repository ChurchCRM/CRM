/**
 * The person page's Login as User item (Actions menu, src/people/views/person-view.php;
 * member sign-in design §6.5): confirms, posts to /admin/api/person/{id}/login-as-user,
 * then opens the Member Portal as the person. A grayed-out item is not wired.
 */

function t(key: string): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  const translated = i18next.t(key);
  return translated === undefined || translated === "" ? key : translated;
}

async function start(item: HTMLElement): Promise<void> {
  const response = await fetch(`${window.CRM?.root ?? ""}/admin/api/person/${item.dataset.personId}/login-as-user`, {
    method: "POST",
    headers: { "content-type": "application/json", "X-CSRF-Token": item.dataset.csrfToken ?? "" },
    body: "{}",
  });
  let body: { message?: string; redirect?: string } = {};
  try {
    body = await response.json();
  } catch {
    // An answer without JSON is reported with the fallback below.
  }
  if (!response.ok || !body.redirect) {
    window.CRM?.notify?.(body.message || t("Login as User could not start."), { type: "danger" });
    return;
  }
  window.location.assign(body.redirect);
}

export function initLoginAsUser(): void {
  const item = document.getElementById("loginAsUser");
  if (!item || item.getAttribute("aria-disabled") === "true") {
    return;
  }

  item.addEventListener("click", (event) => {
    event.preventDefault();
    window.bootbox?.confirm({
      title: t("Login as User"),
      message: t(
        "See the Member Portal as this person sees it? Staff pages stay closed until you press Stop, and the session is recorded on this page and on your user page.",
      ),
      buttons: { confirm: { label: t("Login as User"), className: "btn-warning" } },
      callback: (result: boolean) => {
        if (result) {
          void start(item);
        }
      },
    });
  });
}
