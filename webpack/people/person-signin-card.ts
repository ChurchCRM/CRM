/**
 * The person page's "Portal sign-in" card (src/people/views/partials/portal-signin-card.php):
 * Disable sign-in, Enable sign-in and Sign out everywhere, for administrators,
 * each posting to /admin/api/person/{id}/sign-in/*; and Confirm Email Address,
 * which posts to /api/person/{id}/sign-in/confirm-email. Each reloads the page.
 */

interface ApiError {
  message?: string;
}

function t(key: string): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  const translated = i18next.t(key);
  return translated === undefined || translated === "" ? key : translated;
}

async function postSignInAction(
  card: HTMLElement,
  action: string,
  body: Record<string, unknown> = {},
): Promise<Response> {
  return fetch(`${window.CRM?.root ?? ""}/admin/api/person/${card.dataset.personId}/sign-in/${action}`, {
    method: "POST",
    headers: { "content-type": "application/json", "X-CSRF-Token": card.dataset.csrfToken ?? "" },
    body: JSON.stringify(body),
  });
}

async function errorMessage(response: Response, fallback: string): Promise<string> {
  try {
    const body: ApiError = await response.json();
    return body.message || fallback;
  } catch {
    return fallback;
  }
}

function wireDisable(card: HTMLElement): void {
  const form = document.getElementById("portal-signin-disable-form") as HTMLFormElement | null;
  const reason = document.getElementById("portal-signin-disable-reason") as HTMLTextAreaElement | null;
  const error = document.getElementById("portal-signin-disable-error");
  const confirm = document.getElementById("portal-signin-disable-confirm") as HTMLButtonElement | null;
  if (!form || !reason) {
    return;
  }
  document.getElementById("portal-signin-disable-modal")?.addEventListener("shown.bs.modal", () => reason.focus());

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (error) {
      error.textContent = "";
    }
    if (reason.value.trim() === "") {
      reason.classList.add("is-invalid");
      if (error) {
        error.textContent = t("Say why sign-in is being disabled.");
      }
      return;
    }
    reason.classList.remove("is-invalid");

    if (confirm) {
      confirm.disabled = true;
    }
    const response = await postSignInAction(card, "disable", { reason: reason.value });
    if (confirm) {
      confirm.disabled = false;
    }
    if (!response.ok) {
      if (error) {
        error.textContent = await errorMessage(response, t("Sign-in could not be disabled."));
      }
      return;
    }
    window.CRM?.notify?.(t("Sign-in is disabled for this person."), { type: "success" });
    window.location.reload();
  });
}

function wireEnable(card: HTMLElement): void {
  const button = document.getElementById("portal-signin-enable") as HTMLButtonElement | null;
  button?.addEventListener("click", async () => {
    button.disabled = true;
    const response = await postSignInAction(card, "enable");
    button.disabled = false;
    if (!response.ok) {
      window.CRM?.notify?.(await errorMessage(response, t("Sign-in could not be enabled.")), { type: "danger" });
      return;
    }
    window.CRM?.notify?.(t("Sign-in is enabled for this person."), { type: "success" });
    window.location.reload();
  });
}

function wireSignOutEverywhere(card: HTMLElement): void {
  const button = document.getElementById("portal-signin-sign-out") as HTMLButtonElement | null;
  if (!button) {
    return;
  }

  const signOut = async (): Promise<void> => {
    button.disabled = true;
    const response = await postSignInAction(card, "sign-out-everywhere");
    button.disabled = false;
    if (!response.ok) {
      window.CRM?.notify?.(await errorMessage(response, t("This person could not be signed out.")), { type: "danger" });
      return;
    }
    window.CRM?.notify?.(t("This person is signed out of the Member Portal everywhere."), { type: "success" });
    window.location.reload();
  };

  button.addEventListener("click", () => {
    window.bootbox?.confirm({
      title: t("Sign out everywhere"),
      message: t(
        "End every Member Portal session of this person and forget their remembered devices? Their passkeys stay, so they can sign in again.",
      ),
      buttons: { confirm: { label: t("Sign out everywhere"), className: "btn-danger" } },
      callback: (result: boolean) => {
        if (result) {
          void signOut();
        }
      },
    });
  });
}

function wireConfirmEmail(card: HTMLElement): void {
  const button = document.getElementById("portal-signin-confirm-email") as HTMLButtonElement | null;
  if (!button) {
    return;
  }

  const confirmEmail = async (): Promise<void> => {
    button.disabled = true;
    const response = await fetch(
      `${window.CRM?.root ?? ""}/api/person/${card.dataset.personId}/sign-in/confirm-email`,
      {
        method: "POST",
        headers: { "content-type": "application/json", "X-CSRF-Token": card.dataset.csrfToken ?? "" },
        body: JSON.stringify({ email: button.dataset.email ?? "" }),
      },
    );
    button.disabled = false;
    if (!response.ok) {
      window.CRM?.notify?.(await errorMessage(response, t("The email could not be confirmed.")), { type: "danger" });
      return;
    }
    window.CRM?.notify?.(t("The email is confirmed. It can now be used to sign in."), { type: "success" });
    window.location.reload();
  };

  button.addEventListener("click", () => {
    window.bootbox?.confirm({
      title: t("Confirm Email Address"),
      message: t(
        "Confirm this email only after checking, in person or by phone, that it is this person's own address. Once confirmed, it can be used to sign in to the Member Portal.",
      ),
      buttons: { confirm: { label: t("Confirm Email Address"), className: "btn-primary" } },
      callback: (result: boolean) => {
        if (result) {
          void confirmEmail();
        }
      },
    });
  });
}

export function initPortalSignInCard(): void {
  const card = document.getElementById("portal-signin-card");
  if (!card) {
    return;
  }
  wireDisable(card);
  wireEnable(card);
  wireSignOutEverywhere(card);
  wireConfirmEmail(card);
}
