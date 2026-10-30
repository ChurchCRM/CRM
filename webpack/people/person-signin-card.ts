/**
 * The person page's "Portal sign-in" card (src/people/views/partials/portal-signin-card.php):
 * Disable sign-in, Enable sign-in and Sign out everywhere, for administrators.
 * Each posts to /admin/api/person/{id}/sign-in/* and reloads the page.
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

export function initPortalSignInCard(): void {
  const card = document.getElementById("portal-signin-card");
  if (!card) {
    return;
  }
  wireDisable(card);
  wireEnable(card);
  wireSignOutEverywhere(card);
}
