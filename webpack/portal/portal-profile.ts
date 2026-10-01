/**
 * Member Portal — Profile → Update your details.
 *
 * Loaded by `profile/edit.html.twig` only. It submits the form to
 * `POST /api/portal/me` with fetch so a refused field can be reported next to
 * that field instead of throwing the page away, and it uploads a new photo to
 * `POST /api/portal/me/photo`.
 */
import {
  applyServerFailures,
  clearFieldErrors,
  collectFormValues,
  genericFailureMessage,
  getCsrfToken,
  postPortalJSON,
  setFieldError,
  showPortalToast,
  t,
} from "./portal-forms";

interface PortalProfile {
  [key: string]: unknown;
  firstName?: string;
  lastName?: string;
  photoUrl?: string;
  hasPhoto?: boolean;
}

/** Ten megabytes, the ceiling the photo endpoint documents. */
const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

function root(): string {
  return window.CRM?.root ?? "";
}

/**
 * The checks worth doing before a round trip: the two names the person record
 * insists on, and a birthday that is a real day in the past.
 */
function validateLocally(form: HTMLFormElement): boolean {
  let valid = true;

  for (const field of ["firstName", "lastName"]) {
    const input = form.querySelector<HTMLInputElement>(`[name="${field}"]`);
    if (input && input.value.trim().length < 2) {
      setFieldError(form, field, t("Please enter at least two characters."));
      valid = false;
    }
  }

  const birthday = form.querySelector<HTMLInputElement>('[name="birthday"]');
  if (birthday?.value) {
    const entered = new Date(`${birthday.value}T00:00:00`);
    if (Number.isNaN(entered.getTime())) {
      setFieldError(form, "birthday", t("That birthday is not a valid date."));
      valid = false;
    } else if (entered > new Date()) {
      setFieldError(form, "birthday", t("A birthday cannot be in the future."));
      valid = false;
    }
  }

  return valid;
}

function wireProfileForm(): void {
  const form = document.getElementById("portal-profile-form") as HTMLFormElement | null;
  const saveButton = document.getElementById("portal-profile-save") as HTMLButtonElement | null;
  if (!form) {
    return;
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    clearFieldErrors(form);

    if (!validateLocally(form)) {
      showPortalToast("danger", t("Nothing was saved. Please check the highlighted fields."));
      return;
    }

    if (saveButton) {
      saveButton.disabled = true;
    }

    void postPortalJSON<{ profile: PortalProfile; updated: string[] }>(
      `${root()}/api/portal/me`,
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
          updated.length === 0 ? t("Nothing had changed, so nothing was saved.") : t("Your details have been saved."),
        );
        applyProfile(form, result.data.profile ?? {});
      })
      .catch(() => {
        showPortalToast("danger", t("Nothing was saved. Please try again."));
      })
      .finally(() => {
        if (saveButton) {
          saveButton.disabled = false;
        }
      });
  });
}

/**
 * Put the saved record back into the form, so what is on screen is what the
 * server stored — sanitising may have changed it.
 */
function applyProfile(form: HTMLFormElement, profile: PortalProfile): void {
  for (const input of form.querySelectorAll<HTMLInputElement>("[name]")) {
    const value = profile[input.name];
    if (input.name !== "csrf_token" && typeof value === "string") {
      input.value = value;
    }
  }
  updatePhotoPreview(profile);
}

function updatePhotoPreview(profile: PortalProfile): void {
  const preview = document.getElementById("portal-photo-preview");
  if (!preview || !profile.photoUrl) {
    return;
  }

  if (preview instanceof HTMLImageElement) {
    preview.src = String(profile.photoUrl);
    return;
  }

  // The member had no photo, so the page rendered their initials; swap in the
  // real picture now that there is one.
  const image = document.createElement("img");
  image.id = preview.id;
  image.className = "portal-avatar portal-avatar-lg";
  image.alt = t("Your photo");
  image.src = String(profile.photoUrl);
  preview.replaceWith(image);
}

function wirePhotoUpload(): void {
  const input = document.getElementById("portal-photo-input") as HTMLInputElement | null;
  const form = document.getElementById("portal-profile-form") as HTMLFormElement | null;
  const error = document.getElementById("portal-photo-error");
  if (!input || !form) {
    return;
  }

  input.addEventListener("change", () => {
    const file = input.files?.[0];
    if (error) {
      error.textContent = "";
    }
    if (!file) {
      return;
    }
    if (file.size > MAX_PHOTO_BYTES) {
      if (error) {
        error.textContent = t("That photo is too large. Please choose a smaller one.");
      }
      input.value = "";
      return;
    }

    const reader = new FileReader();
    reader.addEventListener("load", () => {
      void postPortalJSON<{ profile: PortalProfile }>(
        `${root()}/api/portal/me/photo`,
        { imgBase64: String(reader.result ?? "") },
        getCsrfToken(form),
      )
        .then((result) => {
          if (!result.ok) {
            if (error) {
              error.textContent = genericFailureMessage(result);
            }
            return;
          }
          showPortalToast("success", t("Your photo has been saved."));
          updatePhotoPreview(result.data.profile ?? {});
        })
        .catch(() => {
          if (error) {
            error.textContent = t("That photo could not be saved.");
          }
        });
    });
    reader.readAsDataURL(file);
  });
}

function start(): void {
  wireProfileForm();
  wirePhotoUpload();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
