/**
 * Member Portal — the bits every self-service form needs.
 *
 * Shared by the profile and family bundles: one way to send a form to
 * `/api/portal/*`, one way to say "saved", one way to put a message next to the
 * field it belongs to. None of it touches the admin bundle's machinery, so a
 * church theme restyles a portal form by restyling portal CSS.
 */
import i18next from "i18next";

/** What a portal API answers with, whether it worked or not. */
export interface PortalApiResult<T = Record<string, unknown>> {
  ok: boolean;
  status: number;
  data: T & { success?: boolean; message?: string; failures?: string[] };
}

/**
 * The session-wide CSRF token. Every portal form carries it as a hidden input
 * (`csrf_field()` in the template); sending it back in the header is what lets
 * the request body stay a clean JSON document.
 */
export function getCsrfToken(form: HTMLFormElement | null): string {
  const input = form?.querySelector<HTMLInputElement>('input[name="csrf_token"]');
  return input?.value ?? "";
}

/**
 * POST a JSON body to a portal endpoint.
 *
 * Unlike the shared fetch helpers this never throws on a 4xx: the interesting
 * part of a refused save is its body, and the caller wants to show it.
 */
export async function postPortalJSON<T = Record<string, unknown>>(
  url: string,
  body: Record<string, unknown>,
  csrfToken: string,
): Promise<PortalApiResult<T>> {
  const response = await fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": csrfToken,
    },
    body: JSON.stringify(body),
  });

  let data = {} as PortalApiResult<T>["data"];
  try {
    data = await response.json();
  } catch {
    // A response with no JSON body (a session that expired into a redirect,
    // say) still has a status, which is all the caller needs to react.
  }

  return { ok: response.ok, status: response.status, data };
}

/**
 * Every named input in a form, as a plain object. `csrf_token` is left out: it
 * travels in the header, and the portal's allow-lists would ignore it anyway.
 */
export function collectFormValues(form: HTMLFormElement): Record<string, string> {
  const values: Record<string, string> = {};
  for (const [name, value] of new FormData(form).entries()) {
    if (name !== "csrf_token" && typeof value === "string") {
      values[name] = value;
    }
  }
  return values;
}

/** Empty every inline message in a form, before a fresh attempt. */
export function clearFieldErrors(form: HTMLFormElement): void {
  for (const element of form.querySelectorAll<HTMLElement>("[data-error-for]")) {
    element.textContent = "";
  }
  for (const element of form.querySelectorAll<HTMLElement>(".portal-input.is-invalid")) {
    element.classList.remove("is-invalid");
  }
}

/**
 * Put a message next to a field.
 */
export function setFieldError(form: HTMLFormElement, field: string, message: string): boolean {
  const target = form.querySelector<HTMLElement>(`[data-error-for="${CSS.escape(field)}"]`);
  if (!target) {
    return false;
  }
  target.textContent = message;
  form.querySelector<HTMLElement>(`[name="${CSS.escape(field)}"]`)?.classList.add("is-invalid");
  return true;
}

/**
 * Spread the server's validation failures over the fields they name.
 *
 * Propel reports a failure as "Property per_firstname: …", so the field is
 * found by looking for an input whose name appears in the message. Anything
 * that matches no field is handed back so the caller can show it as a
 * form-level message rather than swallowing it.
 */
export function applyServerFailures(form: HTMLFormElement, failures: string[] = []): string[] {
  const unmatched: string[] = [];

  for (const failure of failures) {
    const haystack = failure.toLowerCase();
    let placed = false;
    for (const input of form.querySelectorAll<HTMLInputElement>("[name]")) {
      const name = input.name;
      if (name !== "csrf_token" && haystack.includes(name.toLowerCase())) {
        placed = setFieldError(form, name, failure);
        break;
      }
    }
    if (!placed) {
      unmatched.push(failure);
    }
  }

  return unmatched;
}

/**
 * A one-shot message at the top of the page, in the same clothes as the
 * server-rendered flash messages so a theme styles both at once.
 */
export function showPortalToast(type: "success" | "danger" | "warning" | "info", message: string): void {
  const container = document.querySelector<HTMLElement>(".portal-container") ?? document.body;
  let list = document.querySelector<HTMLElement>(".portal-flash-list");
  if (!list) {
    list = document.createElement("div");
    list.className = "portal-flash-list";
    container.prepend(list);
  }

  const flash = document.createElement("div");
  flash.className = `portal-flash portal-flash-${type}`;
  flash.setAttribute("role", "status");

  const text = document.createElement("span");
  text.className = "portal-flash-text";
  text.textContent = message;

  const dismiss = document.createElement("button");
  dismiss.type = "button";
  dismiss.className = "portal-flash-dismiss";
  dismiss.setAttribute("aria-label", i18next.t("Dismiss"));
  dismiss.textContent = "×";
  dismiss.addEventListener("click", () => flash.remove());

  flash.append(text, dismiss);
  list.append(flash);
  flash.scrollIntoView({ block: "nearest" });
}

/**
 * The message to show when a save came back refused and the server had nothing
 * more specific to say.
 */
export function genericFailureMessage(result: PortalApiResult): string {
  if (result.data.message) {
    return result.data.message;
  }
  if (result.status === 403) {
    return i18next.t("You are not allowed to change this. Please contact the church office.");
  }
  return i18next.t("Nothing was saved. Please try again.");
}

/**
 * Re-render the read-only values a page shows for the fields that just
 * changed, so the page agrees with what was saved without a reload.
 */
export function refreshDisplayedValues(values: Record<string, unknown>): void {
  for (const element of document.querySelectorAll<HTMLElement>("[data-field]")) {
    const field = element.dataset.field;
    if (field && field in values) {
      const value = values[field];
      element.textContent = value === null || value === undefined || value === "" ? "—" : String(value);
    }
  }
}
