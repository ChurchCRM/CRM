/**
 * Member Portal — My Family.
 *
 * One bundle for the three family pages, because each of them is a small form
 * against `/api/portal/family*` and they share the same handling:
 *
 *  - `family/edit.html.twig`    → POST /api/portal/family
 *  - `family/confirm.html.twig` → POST /api/portal/family/confirm
 *  - `family/index.html.twig`   → POST /api/portal/family/members (the dialog)
 *
 * Each `wire*` function returns immediately when its form is not on the page,
 * so the same file is correct on all three.
 */
import i18next from "i18next";

import {
  applyServerFailures,
  clearFieldErrors,
  collectFormValues,
  genericFailureMessage,
  getCsrfToken,
  postPortalJSON,
  refreshDisplayedValues,
  setFieldError,
  showPortalToast,
} from "./portal-forms";

interface PortalFamily {
  [key: string]: unknown;
}

function root(): string {
  return window.CRM?.root ?? "";
}

/** A date input that must be a real day, and not in the future. */
function validateDate(form: HTMLFormElement, field: string, futureMessage: string): boolean {
  const input = form.querySelector<HTMLInputElement>(`[name="${field}"]`);
  if (!input?.value) {
    return true;
  }
  const entered = new Date(`${input.value}T00:00:00`);
  if (Number.isNaN(entered.getTime())) {
    setFieldError(form, field, i18next.t("That is not a valid date."));
    return false;
  }
  if (entered > new Date()) {
    setFieldError(form, field, futureMessage);
    return false;
  }
  return true;
}

function wireFamilyForm(): void {
  const form = document.getElementById("portal-family-form") as HTMLFormElement | null;
  const saveButton = document.getElementById("portal-family-save") as HTMLButtonElement | null;
  if (!form) {
    return;
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    clearFieldErrors(form);

    if (!validateDate(form, "weddingDate", i18next.t("A wedding date cannot be in the future."))) {
      showPortalToast("danger", i18next.t("Nothing was saved. Please check the highlighted fields."));
      return;
    }

    if (saveButton) {
      saveButton.disabled = true;
    }

    void postPortalJSON<{ family: PortalFamily; updated: string[] }>(
      `${root()}/api/portal/family`,
      collectFormValues(form),
      getCsrfToken(form),
    )
      .then((result) => {
        if (!result.ok) {
          const unmatched = applyServerFailures(form, result.data.failures ?? []);
          showPortalToast("danger", unmatched[0] ?? genericFailureMessage(result));
          return;
        }

        const updated = result.data.updated ?? [];
        showPortalToast(
          "success",
          updated.length === 0
            ? i18next.t("Nothing had changed, so nothing was saved.")
            : i18next.t("Your family details have been saved."),
        );

        const family = result.data.family ?? {};
        for (const input of form.querySelectorAll<HTMLInputElement>("[name]")) {
          const value = family[input.name];
          if (input.name !== "csrf_token" && typeof value === "string") {
            input.value = value;
          }
        }
        refreshDisplayedValues(family);
      })
      .catch(() => {
        showPortalToast("danger", i18next.t("Nothing was saved. Please try again."));
      })
      .finally(() => {
        if (saveButton) {
          saveButton.disabled = false;
        }
      });
  });
}

function wireConfirmForm(): void {
  const form = document.getElementById("portal-confirm-form") as HTMLFormElement | null;
  const submit = document.getElementById("portal-confirm-submit") as HTMLButtonElement | null;
  const commentField = document.getElementById("portal-confirm-comment-field");
  if (!form) {
    return;
  }

  // The comment box only makes sense once the member says something is wrong.
  const syncComment = () => {
    const needsChange = form.querySelector<HTMLInputElement>('[name="result"][value="change-needed"]')?.checked;
    if (commentField) {
      commentField.hidden = !needsChange;
    }
  };
  for (const radio of form.querySelectorAll<HTMLInputElement>('[name="result"]')) {
    radio.addEventListener("change", syncComment);
  }
  syncComment();

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    clearFieldErrors(form);

    const values = collectFormValues(form);
    if (values.result === "change-needed" && !(values.comment ?? "").trim()) {
      setFieldError(form, "comment", i18next.t("Please tell us what needs changing."));
      return;
    }

    if (submit) {
      submit.disabled = true;
    }

    void postPortalJSON(`${root()}/api/portal/family/confirm`, values, getCsrfToken(form))
      .then((result) => {
        if (!result.ok) {
          showPortalToast("danger", genericFailureMessage(result));
          return;
        }
        showPortalToast("success", i18next.t("Thank you. The church office has your answer."));
        form.hidden = true;
      })
      .catch(() => {
        showPortalToast("danger", i18next.t("Nothing was saved. Please try again."));
      })
      .finally(() => {
        if (submit) {
          submit.disabled = false;
        }
      });
  });
}

function wireAddMemberDialog(): void {
  const dialog = document.getElementById("portal-add-member-dialog") as HTMLDialogElement | null;
  const form = document.getElementById("portal-add-member-form") as HTMLFormElement | null;
  const open = document.getElementById("portal-add-member-open");
  const cancel = document.getElementById("portal-add-member-cancel");
  const submit = document.getElementById("portal-add-member-submit") as HTMLButtonElement | null;
  if (!dialog || !form) {
    return;
  }

  open?.addEventListener("click", () => {
    clearFieldErrors(form);
    if (typeof dialog.showModal === "function") {
      dialog.showModal();
    } else {
      dialog.setAttribute("open", "open");
    }
  });

  const close = () => {
    if (typeof dialog.close === "function") {
      dialog.close();
    } else {
      dialog.removeAttribute("open");
    }
  };
  cancel?.addEventListener("click", close);

  submit?.addEventListener("click", () => {
    clearFieldErrors(form);

    const values = collectFormValues(form);
    let valid = true;
    for (const field of ["firstName", "lastName"]) {
      if ((values[field] ?? "").trim().length < 2) {
        setFieldError(form, field, i18next.t("Please enter at least two characters."));
        valid = false;
      }
    }
    if (!validateDate(form, "birthday", i18next.t("A birthday cannot be in the future."))) {
      valid = false;
    }
    if (!valid) {
      return;
    }

    submit.disabled = true;
    void postPortalJSON<{ personId: number }>(`${root()}/api/portal/family/members`, values, getCsrfToken(form))
      .then((result) => {
        if (!result.ok) {
          const unmatched = applyServerFailures(form, result.data.failures ?? []);
          showPortalToast("danger", unmatched[0] ?? genericFailureMessage(result));
          return;
        }
        close();
        showPortalToast("success", i18next.t("Thank you. The church office will review this before it is added."));
        form.reset();
      })
      .catch(() => {
        showPortalToast("danger", i18next.t("Nothing was saved. Please try again."));
      })
      .finally(() => {
        submit.disabled = false;
      });
  });
}

function start(): void {
  wireFamilyForm();
  wireConfirmForm();
  wireAddMemberDialog();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
