/**
 * The person page's portal devices, passkeys and permissions
 * (src/people/views/partials/portal-signin-access.php), for administrators:
 * sign out a remembered device, remove a passkey or a given permission, and give one.
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

async function post(card: HTMLElement, path: string, body: Record<string, unknown> = {}): Promise<Response> {
  return fetch(`${window.CRM?.root ?? ""}/admin/api/person/${card.dataset.personId}/sign-in/${path}`, {
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

const REMOVALS: Array<{
  selector: string;
  data: string;
  path: (id: string) => string;
  title: string;
  message: string;
  done: string;
}> = [
  {
    selector: ".portal-signin-remove-device",
    data: "deviceId",
    path: (id) => `devices/${id}/remove`,
    title: "Sign out this device",
    message:
      "This device will no longer keep the person signed in, and a session open on it ends. They can sign in again by email.",
    done: "The device is signed out.",
  },
  {
    selector: ".portal-signin-remove-passkey",
    data: "passkeyId",
    path: (id) => `passkeys/${id}/remove`,
    title: "Remove this passkey",
    message: "This passkey will no longer sign the person in. They can sign in by email and add a new one.",
    done: "The passkey was removed.",
  },
  {
    selector: ".portal-signin-remove-grant",
    data: "grantId",
    path: (id) => `grants/${id}/remove`,
    title: "Remove this permission",
    message: "The person loses this permission in the Member Portal at once.",
    done: "The permission was removed.",
  },
];

function wireRemovals(card: HTMLElement): void {
  for (const removal of REMOVALS) {
    for (const button of card.querySelectorAll<HTMLButtonElement>(removal.selector)) {
      button.addEventListener("click", () => {
        const id = button.dataset[removal.data] ?? "";
        window.bootbox?.confirm({
          title: t(removal.title),
          message: t(removal.message),
          buttons: { confirm: { label: t(removal.title), className: "btn-danger" } },
          callback: async (confirmed: boolean) => {
            if (!confirmed) {
              return;
            }
            button.disabled = true;
            const response = await post(card, removal.path(id));
            button.disabled = false;
            if (!response.ok) {
              window.CRM?.notify?.(await errorMessage(response, t("That could not be removed.")), { type: "danger" });
              return;
            }
            window.CRM?.notify?.(t(removal.done), { type: "success" });
            window.location.reload();
          },
        });
      });
    }
  }
}

function wireGiveGrant(card: HTMLElement): void {
  const form = document.getElementById("portal-grant-form") as HTMLFormElement | null;
  const capability = document.getElementById("portal-grant-capability") as HTMLSelectElement | null;
  const scopeGroup = document.getElementById("portal-grant-scope-group");
  const scopeLabel = document.getElementById("portal-grant-scope-label");
  const scope = document.getElementById("portal-grant-scope") as HTMLSelectElement | null;
  const hint = document.getElementById("portal-grant-capability-hint");
  const ends = document.getElementById("portal-grant-ends") as HTMLInputElement | null;
  const reason = document.getElementById("portal-grant-reason") as HTMLTextAreaElement | null;
  const error = document.getElementById("portal-grant-error");
  const save = document.getElementById("portal-grant-save") as HTMLButtonElement | null;
  if (!form || !capability || !scope || !reason) {
    return;
  }

  let scopeType = "none";
  let scopeSelect: TomSelectInstance | null = null;

  if (ends) {
    const today = new Date();
    const pad = (n: number): string => String(n).padStart(2, "0");
    ends.min = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
  }

  const buildScopeSelect = (): void => {
    scopeSelect?.destroy();
    scopeSelect = null;
    scope.innerHTML = "";
    if (scopeType === "none" || typeof window.TomSelect !== "function") {
      return;
    }
    const type = scopeType;
    scopeSelect = new window.TomSelect(scope, {
      valueField: "id",
      labelField: "text",
      searchField: "text",
      dropdownParent: "body",
      maxOptions: null,
      preload: "focus",
      placeholder: scope.dataset.placeholder,
      load: (query: string, callback: (results?: Array<{ id: number; text: string }>) => void) => {
        fetch(`${window.CRM?.root ?? ""}/admin/api/member-portal/grant-scopes/${type}?q=${encodeURIComponent(query)}`, {
          credentials: "same-origin",
        })
          .then((response) => (response.ok ? response.json() : []))
          .then((choices: Array<{ id: number; text: string }>) => callback(choices))
          .catch(() => callback());
      },
    });
  };

  capability.addEventListener("change", () => {
    const option = capability.selectedOptions[0];
    scopeType = option?.dataset.scopeType || "none";
    if (hint) {
      hint.textContent = [option?.dataset.description ?? "", option?.dataset.status ?? ""].filter(Boolean).join(" ");
    }
    scopeGroup?.classList.toggle("d-none", scopeType === "none");
    if (scopeLabel) {
      scopeLabel.textContent = option?.dataset.scopeLabel ?? "";
    }
    buildScopeSelect();
  });

  document.getElementById("portal-grant-modal")?.addEventListener("shown.bs.modal", () => capability.focus());

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (error) {
      error.textContent = "";
    }
    const scopeId = scopeSelect ? String(scopeSelect.getValue() || "") : "";
    const problems: Array<[HTMLElement, boolean]> = [
      [capability, capability.value === ""],
      [reason, reason.value.trim() === ""],
    ];
    for (const [field, invalid] of problems) {
      field.classList.toggle("is-invalid", invalid);
    }
    if (problems.some(([, invalid]) => invalid) || (scopeType !== "none" && scopeId === "")) {
      if (error) {
        error.textContent = t("Choose a permission, what it is for, and say why.");
      }
      return;
    }

    if (save) {
      save.disabled = true;
    }
    const response = await post(card, "grants", {
      capability: capability.value,
      scopeId: scopeType === "none" ? null : Number(scopeId),
      endsOn: ends?.value ?? "",
      reason: reason.value,
    });
    if (save) {
      save.disabled = false;
    }
    if (!response.ok) {
      if (error) {
        error.textContent = await errorMessage(response, t("The permission could not be given."));
      }
      return;
    }
    window.CRM?.notify?.(t("The permission was given."), { type: "success" });
    window.location.reload();
  });
}

export function initPortalAccess(): void {
  const card = document.getElementById("portal-signin-card");
  if (!card || !document.getElementById("portal-signin-access")) {
    return;
  }
  wireRemovals(card);
  wireGiveGrant(card);
}
