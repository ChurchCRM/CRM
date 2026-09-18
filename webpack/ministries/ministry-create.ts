/**
 * "New ministry" — the modal that replaced the guided setup wizard.
 *
 * The wizard (S2, design §5.3) was the only way to create a ministry, and most of
 * what it walked a coordinator through — teams, positions, qualifications,
 * schedules, staffing — now has a better home on the ministry page itself. What
 * was genuinely missing there was the very first step, so that step is this: a
 * name, a description, and a jump straight to the new ministry's page, where the
 * team and the pool Group it was created with are already waiting (D18/D19).
 *
 * It lives in the dashboard's quick actions. It was shared with the ministries
 * list page until that page was retired in favour of the sidebar's Ministries
 * heading, which is why this is still its own module rather than part of
 * `dashboard.ts`: the modal is a self-contained thing and the next page that
 * needs it imports it the same way.
 *
 * Creating a ministry is manager-only (§4.6), so the BUTTON and the modal are
 * rendered server-side for a manager only; this module simply does nothing when
 * the markup is not on the page. Hiding is not security (D5): `POST
 * /api/ministries/ministries` is gated by `ManageMinistriesRoleAuthMiddleware`
 * whatever the page chose to draw.
 *
 * Every user-visible string is `i18next.t()` here rather than in the view: the
 * extractor scans only `webpack/**` and `src/skin/js/**` (design §5.10, F31).
 */

import { createMinistry, errorMessage, notifyError, notifySuccess } from "./api";

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}

function modal(id: string): { show(): void; hide(): void } | null {
  const el = byId(id);
  if (!el || !window.bootstrap?.Modal) {
    return null;
  }

  return window.bootstrap.Modal.getOrCreateInstance(el);
}

function showError(message: string): void {
  const box = byId("ministry-create-form-error");
  const text = box?.querySelector(".volunteer-error-text");
  if (text) {
    text.textContent = message;
  }
  show(box, true);
  notifyError(message);
}

function save(): void {
  const name = byId<HTMLInputElement>("ministry-create-name")?.value.trim() ?? "";
  const description = byId<HTMLInputElement>("ministry-create-description")?.value.trim() ?? "";

  if (name === "") {
    showError(i18next.t("Give the ministry a name"));
    return;
  }

  createMinistry(name, description)
    .then((result) => {
      notifySuccess(i18next.t("Ministry created"));
      // Straight to the new ministry's page: its first team and its pool Group
      // exist already, so there is nothing else to ask for here.
      window.location.href = `${window.CRM?.root ?? ""}/ministries/${result.ministry.id}`;
    })
    .catch((error: unknown) => {
      showError(errorMessage(error, i18next.t("The ministry could not be created")));
    });
}

/** Wire the button and the modal, if this page carries them. */
export function initMinistryCreate(): void {
  const button = byId("ministry-new-btn");
  const empty = byId("ministry-new-empty-btn");
  if (!button && !empty) {
    return;
  }

  const open = (): void => {
    show(byId("ministry-create-form-error"), false);
    const name = byId<HTMLInputElement>("ministry-create-name");
    const description = byId<HTMLInputElement>("ministry-create-description");
    if (name) {
      name.value = "";
    }
    if (description) {
      description.value = "";
    }
    modal("ministryCreateModal")?.show();
  };

  button?.addEventListener("click", open);
  empty?.addEventListener("click", open);
  byId("ministry-create-save")?.addEventListener("click", save);

  // Focus the first field once the modal has finished animating. Without it
  // Bootstrap's own `shown.bs.modal` handler moves focus to the dialog partway
  // through the 150 ms fade and swallows whatever was typed in the meantime.
  byId("ministryCreateModal")?.addEventListener("shown.bs.modal", () => {
    byId<HTMLInputElement>("ministry-create-name")?.focus();
  });
}
