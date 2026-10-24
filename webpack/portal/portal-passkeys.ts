/**
 * Member Portal passkeys (member sign-in design §6.3), on the browser's own
 * WebAuthn API.
 *
 * - `[data-passkey-add]` (Sign-in and devices, and the one-time offer after an
 *   email sign-in): creates a passkey, sends it to the element's
 *   `data-add-url`, and reloads the page, which shows the server's notice.
 * - `[data-passkey-sign-in]` (`/portal/login`): the "Sign in with a passkey"
 *   button, and the browser's passkey autofill on the email box. The answer is
 *   posted in `#portalPasskeyForm`, so the server decides where to go next.
 *
 * Binary fields travel as base64url both ways.
 */
import { postPortalJSON, t } from "./portal-forms";

interface CredentialDescriptorJSON {
  id: string;
  type: PublicKeyCredentialType;
  transports?: AuthenticatorTransport[];
}

interface CreationOptionsJSON
  extends Omit<PublicKeyCredentialCreationOptions, "challenge" | "user" | "excludeCredentials"> {
  challenge: string;
  user: { id: string; name: string; displayName: string };
  excludeCredentials?: CredentialDescriptorJSON[];
}

interface RequestOptionsJSON extends Omit<PublicKeyCredentialRequestOptions, "challenge" | "allowCredentials"> {
  challenge: string;
  allowCredentials?: CredentialDescriptorJSON[];
}

interface OptionsAnswer<T> {
  publicKey?: T;
  message?: string;
}

/** Sign-in options are good for ten minutes; the autofill request is renewed before then. */
const AUTOFILL_RENEW_MS = 9 * 60 * 1000;

function fromBase64Url(value: string): ArrayBuffer {
  const base64 = value.replace(/-/g, "+").replace(/_/g, "/");
  const binary = atob(base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), "="));
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes.buffer;
}

function toBase64Url(buffer: ArrayBuffer): string {
  let binary = "";
  for (const byte of new Uint8Array(buffer)) {
    binary += String.fromCharCode(byte);
  }
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

function descriptors(list: CredentialDescriptorJSON[] | undefined): PublicKeyCredentialDescriptor[] {
  return (list ?? []).map((item) => ({ ...item, id: fromBase64Url(item.id) }));
}

function csrfToken(): string {
  return document.querySelector<HTMLInputElement>('input[name="csrf_token"]')?.value ?? "";
}

function supportsPasskeys(): boolean {
  return typeof window.PublicKeyCredential === "function" && typeof navigator.credentials?.create === "function";
}

function showError(container: HTMLElement, message: string): void {
  const target = container.querySelector<HTMLElement>("[data-passkey-error]");
  if (target) {
    target.textContent = message;
  }
}

function addFailureMessage(error: unknown): string {
  const name = error instanceof DOMException ? error.name : "";
  if (name === "InvalidStateError") {
    return t("This device already has a passkey for you.");
  }
  if (name === "NotAllowedError" || name === "AbortError") {
    return t("No passkey was added.");
  }
  return t("The passkey could not be added. Please try again.");
}

async function addPasskey(container: HTMLElement, button: HTMLButtonElement): Promise<void> {
  showError(container, "");
  button.disabled = true;
  try {
    const options = await postPortalJSON<OptionsAnswer<CreationOptionsJSON>>(
      container.dataset.optionsUrl ?? "",
      {},
      csrfToken(),
    );
    const json = options.data.publicKey;
    if (!options.ok || !json) {
      showError(container, options.data.message || t("The passkey could not be added. Please try again."));
      return;
    }

    let credential: Credential | null;
    try {
      credential = await navigator.credentials.create({
        publicKey: {
          ...json,
          challenge: fromBase64Url(json.challenge),
          user: { ...json.user, id: fromBase64Url(json.user.id) },
          excludeCredentials: descriptors(json.excludeCredentials),
        },
      });
    } catch (error) {
      showError(container, addFailureMessage(error));
      return;
    }
    if (!(credential instanceof PublicKeyCredential)) {
      showError(container, t("No passkey was added."));
      return;
    }

    const response = credential.response as AuthenticatorAttestationResponse;
    const saved = await postPortalJSON(
      container.dataset.addUrl ?? "",
      {
        clientDataJSON: toBase64Url(response.clientDataJSON),
        attestationObject: toBase64Url(response.attestationObject),
        transports: typeof response.getTransports === "function" ? response.getTransports() : [],
      },
      csrfToken(),
    );
    if (!saved.ok) {
      showError(container, saved.data.message || t("The passkey could not be added. Please try again."));
      return;
    }
    window.location.reload();
  } catch {
    showError(container, t("The passkey could not be added. Please try again."));
  } finally {
    button.disabled = false;
  }
}

async function wireAdd(container: HTMLElement): Promise<void> {
  if (container.dataset.passkeyBound) {
    return;
  }
  container.dataset.passkeyBound = "1";
  const button = container.querySelector<HTMLButtonElement>("[data-passkey-create]");
  if (!button) {
    return;
  }

  const isOffer = container.hasAttribute("data-passkey-offer");
  if (!supportsPasskeys()) {
    if (!isOffer) {
      button.disabled = true;
      showError(container, t("This browser cannot make passkeys."));
    }
    return;
  }
  if (isOffer) {
    const canVerify = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);
    if (!canVerify) {
      return;
    }
    container.hidden = false;
    container.querySelector("[data-passkey-dismiss]")?.addEventListener("click", () => {
      container.hidden = true;
    });
  }

  button.addEventListener("click", () => {
    void addPasskey(container, button);
  });
}

function submitAssertion(credential: PublicKeyCredential): void {
  const form = document.getElementById("portalPasskeyForm") as HTMLFormElement | null;
  if (!form) {
    return;
  }
  const response = credential.response as AuthenticatorAssertionResponse;
  const values: Record<string, string> = {
    credentialId: toBase64Url(credential.rawId),
    clientDataJSON: toBase64Url(response.clientDataJSON),
    authenticatorData: toBase64Url(response.authenticatorData),
    signature: toBase64Url(response.signature),
    userHandle: response.userHandle ? toBase64Url(response.userHandle) : "",
  };
  for (const [name, value] of Object.entries(values)) {
    const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
    if (input) {
      input.value = value;
    }
  }
  form.submit();
}

function wireSignIn(container: HTMLElement): void {
  if (!supportsPasskeys() || typeof navigator.credentials?.get !== "function") {
    return;
  }
  container.hidden = false;
  const optionsUrl = container.dataset.optionsUrl ?? "";
  const button = document.getElementById("portalPasskeySignInButton") as HTMLButtonElement | null;
  let pending: AbortController | null = null;
  let renewTimer: number | undefined;

  const ask = async (autofill: boolean): Promise<void> => {
    pending?.abort();
    window.clearTimeout(renewTimer);
    const controller = new AbortController();
    pending = controller;

    const options = await postPortalJSON<OptionsAnswer<RequestOptionsJSON>>(optionsUrl, {}, csrfToken());
    const json = options.data.publicKey;
    if (controller.signal.aborted) {
      return;
    }
    if (!options.ok || !json) {
      if (!autofill) {
        showError(container, options.data.message || t("Passkeys cannot be used here."));
      }
      return;
    }

    if (autofill) {
      renewTimer = window.setTimeout(() => void ask(true), AUTOFILL_RENEW_MS);
    }
    try {
      const credential = await navigator.credentials.get({
        publicKey: {
          ...json,
          challenge: fromBase64Url(json.challenge),
          allowCredentials: descriptors(json.allowCredentials),
        },
        signal: controller.signal,
        ...(autofill ? { mediation: "conditional" as CredentialMediationRequirement } : {}),
      });
      if (credential instanceof PublicKeyCredential) {
        window.clearTimeout(renewTimer);
        submitAssertion(credential);
      }
    } catch {
      if (controller.signal.aborted) {
        return;
      }
      if (!autofill) {
        showError(container, t("No passkey was used. Try again, or sign in with your email address."));
        void startAutofill();
      }
    }
  };

  const startAutofill = async (): Promise<void> => {
    const available =
      typeof PublicKeyCredential.isConditionalMediationAvailable === "function" &&
      (await PublicKeyCredential.isConditionalMediationAvailable().catch(() => false));
    if (available) {
      await ask(true);
    }
  };

  button?.addEventListener("click", () => {
    showError(container, "");
    void ask(false);
  });
  void startAutofill();
}

document.addEventListener("DOMContentLoaded", () => {
  for (const container of document.querySelectorAll<HTMLElement>("[data-passkey-add]")) {
    void wireAdd(container);
  }
  const signIn = document.querySelector<HTMLElement>("[data-passkey-sign-in]");
  if (signIn) {
    wireSignIn(signIn);
  }
});
