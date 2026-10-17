/**
 * Member Portal — subscribing to the church calendar (design §5.3).
 *
 * A member ticks the calendars they want and gets one address to paste into
 * Apple Calendar, Google Calendar or Outlook. The address is a bearer secret,
 * which shapes three decisions here:
 *
 *   1. it is fetched when the dialog opens, never rendered into the page, so it
 *      is not sitting in the HTML of every calendar page load;
 *   2. it is shown in a read-only input rather than as a link, so a stray click
 *      cannot navigate to it and leave it in browser history;
 *   3. "Reset link" asks first, because it silently breaks whatever the member
 *      already added to their phone.
 *
 * The dialog is a native `<dialog>` in the portal's own chrome — the same
 * pattern the family page's "add a family member" dialog uses — rather than
 * bootbox or a Bootstrap modal, so the portal keeps one dialog style that a
 * church theme restyles with the portal tokens.
 */
import { getCsrfToken, type PortalApiResult, sendPortalJSON, t } from "./portal-forms";

/** One calendar the member may tick. */
interface SubscriptionChoice {
  id: string;
  name: string;
  color: string;
  selected: boolean;
}

/** What all three subscription routes answer with. */
interface Subscription {
  title: string;
  url: string | null;
  webcalUrl: string | null;
  choices: SubscriptionChoice[];
}

interface SubscriptionConfig {
  subscriptionUrl: string;
  subscriptionResetUrl: string;
}

const DIALOG_ID = "portal-calendar-subscribe-dialog";
const RESET_DIALOG_ID = "portal-calendar-reset-dialog";

function element<T extends HTMLElement = HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

/** `showModal()` where it exists, the `open` attribute where it does not. */
function openDialog(dialog: HTMLDialogElement): void {
  if (typeof dialog.showModal === "function") {
    dialog.showModal();
  } else {
    dialog.setAttribute("open", "open");
  }
}

function closeDialog(dialog: HTMLDialogElement): void {
  if (typeof dialog.close === "function") {
    dialog.close();
  } else {
    dialog.removeAttribute("open");
  }
}

function setError(message: string): void {
  const target = element("portal-calendar-subscribe-error");
  if (target) {
    target.textContent = message;
  }
  if (message !== "") {
    setNotice("");
  }
}

/**
 * Say what just happened, inside the dialog.
 *
 * Not `showPortalToast`: a `<dialog>` opened with `showModal()` is in the
 * browser's top layer, so a message appended to the page underneath would be
 * drawn behind the modal backdrop where nobody sees it.
 */
function setNotice(message: string): void {
  const target = element("portal-calendar-subscribe-notice");
  if (target) {
    target.textContent = message;
  }
}

/**
 * Draw one checkbox per calendar the church shares. Rebuilt on every answer
 * from the server rather than patched, so an administrator un-sharing a
 * calendar between two saves cannot leave a stale row behind.
 */
function renderChoices(choices: SubscriptionChoice[]): void {
  const container = element("portal-calendar-subscribe-choices");
  if (!container) {
    return;
  }

  container.textContent = "";

  if (choices.length === 0) {
    const empty = document.createElement("p");
    empty.className = "portal-card-text";
    empty.textContent = t("No calendar has been shared with members yet.");
    container.append(empty);
    return;
  }

  for (const choice of choices) {
    const label = document.createElement("label");
    label.className = "portal-choice";

    const input = document.createElement("input");
    input.type = "checkbox";
    input.value = choice.id;
    input.checked = choice.selected;

    const swatch = document.createElement("span");
    swatch.className = "portal-calendar-legend-swatch";
    swatch.style.backgroundColor = choice.color;
    swatch.setAttribute("aria-hidden", "true");

    const name = document.createElement("span");
    // textContent, never innerHTML: a calendar name is church-entered text.
    name.textContent = choice.name;

    label.append(input, swatch, name);
    container.append(label);
  }
}

/** Fill in the address half of the dialog, or hide it when there is no feed yet. */
function renderAddress(subscription: Subscription): void {
  const result = element("portal-calendar-subscribe-result");
  const url = element<HTMLInputElement>("portal-calendar-subscribe-url");
  const open = element<HTMLAnchorElement>("portal-calendar-subscribe-open");
  const openRow = element("portal-calendar-subscribe-open-row");
  const manualHint = element("portal-calendar-subscribe-manual-hint");
  if (!result) {
    return;
  }

  if (!subscription.url) {
    result.hidden = true;
    return;
  }

  if (url) {
    url.value = subscription.url;
  }

  // "Open in calendar app" is a webcal:// link, and webcal:// is only a
  // one-tap shortcut where the feed is served over https. macOS and iOS
  // Calendar rewrite webcal:// to https:// before they fetch anything, and
  // they do not fall back to http: on a church still on plain http the tap
  // sends a TLS handshake to port 80, the server answers with nothing it can
  // read, and the subscription fails with no useful message. So over http we
  // do not offer the link at all — the member pastes the address instead,
  // which works everywhere.
  const secureFeed = subscription.url.startsWith("https://");
  if (openRow) {
    openRow.hidden = !secureFeed;
  }
  if (manualHint) {
    manualHint.hidden = secureFeed;
  }
  if (open && secureFeed) {
    open.href = subscription.webcalUrl ?? subscription.url;
  }

  result.hidden = false;
}

function render(subscription: Subscription): void {
  renderChoices(subscription.choices);
  renderAddress(subscription);
}

/** The ids of the ticked checkboxes, in the order they are drawn. */
function checkedIds(): string[] {
  const container = element("portal-calendar-subscribe-choices");
  if (!container) {
    return [];
  }

  return Array.from(container.querySelectorAll<HTMLInputElement>("input[type=checkbox]"))
    .filter((input) => input.checked)
    .map((input) => input.value);
}

/**
 * Turn a refused call into a message in the dialog. A portal API answers
 * `{message}`; anything else gets a sentence that still tells the member what
 * to do next.
 */
function failureMessage<T>(result: PortalApiResult<T>): string {
  if (result.data.message) {
    return result.data.message;
  }
  if (result.status === 403) {
    return t("Your session has expired. Please sign in again.");
  }
  return t("Nothing was saved. Please try again.");
}

/**
 * Copy the address. `navigator.clipboard` needs a secure context, which a
 * church on plain HTTP does not have — so when it is missing or refused, the
 * fallback selects the text and lets the member press the key combination
 * their device uses.
 */
async function copyAddress(): Promise<void> {
  const input = element<HTMLInputElement>("portal-calendar-subscribe-url");
  if (!input || input.value === "") {
    return;
  }

  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(input.value);
      setNotice(t("The calendar address was copied."));
      return;
    }
  } catch {
    // Fall through to the selection fallback below.
  }

  input.focus();
  input.select();
  input.setSelectionRange(0, input.value.length);
  setNotice(t("The address is selected — copy it with your keyboard."));
}

export function initCalendarSubscription(config: SubscriptionConfig): void {
  const dialog = element<HTMLDialogElement>(DIALOG_ID);
  const resetDialog = element<HTMLDialogElement>(RESET_DIALOG_ID);
  const openButton = element("portal-calendar-subscribe");
  const form = element<HTMLFormElement>("portal-calendar-subscribe-form");
  if (!dialog || !openButton) {
    return;
  }

  const csrf = () => getCsrfToken(form);

  /** Ask the server for the current state and redraw the dialog from it. */
  const load = async (): Promise<void> => {
    setError("");
    setNotice("");
    const response = await fetch(config.subscriptionUrl, {
      credentials: "same-origin",
      headers: { accept: "application/json" },
    });
    if (!response.ok) {
      setError(t("The calendars could not be loaded. Please try again."));
      return;
    }
    render((await response.json()) as Subscription);
  };

  openButton.addEventListener("click", () => {
    openDialog(dialog);
    void load();
  });

  element("portal-calendar-subscribe-close")?.addEventListener("click", () => closeDialog(dialog));

  element("portal-calendar-subscribe-save")?.addEventListener("click", () => {
    setError("");

    const calendars = checkedIds();
    if (calendars.length === 0) {
      setError(t("Choose at least one calendar to subscribe to."));
      return;
    }

    void sendPortalJSON<Subscription>("PUT", config.subscriptionUrl, { calendars }, csrf()).then((result) => {
      if (!result.ok) {
        setError(failureMessage(result));
        return;
      }
      render(result.data);
      setNotice(t("Your calendar subscription was saved."));
    });
  });

  element("portal-calendar-subscribe-copy")?.addEventListener("click", () => {
    void copyAddress();
  });

  // Reset: confirm first, because it breaks an address the member has already
  // added to a device.
  element("portal-calendar-subscribe-reset")?.addEventListener("click", () => {
    if (resetDialog) {
      openDialog(resetDialog);
    }
  });

  element("portal-calendar-reset-cancel")?.addEventListener("click", () => {
    if (resetDialog) {
      closeDialog(resetDialog);
    }
  });

  element("portal-calendar-reset-confirm")?.addEventListener("click", () => {
    if (resetDialog) {
      closeDialog(resetDialog);
    }
    setError("");

    void sendPortalJSON<Subscription>("POST", config.subscriptionResetUrl, {}, csrf()).then((result) => {
      if (!result.ok) {
        setError(failureMessage(result));
        return;
      }
      render(result.data);
      setNotice(t("You have a new calendar address. The old one no longer works."));
    });
  });
}
