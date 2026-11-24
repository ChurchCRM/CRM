/**
 * The one shared copy-to-clipboard helper (#11458).
 *
 * Clipboard API first, then the hidden-textarea `document.execCommand("copy")`
 * fallback for plain-HTTP (insecure) contexts, where `navigator.clipboard` is
 * missing or refuses. Tabler's Clipboard component has no such fallback, so
 * it is not a drop-in replacement on its own.
 *
 * Transport only: returns whether the text was copied, and owns no UI. Each
 * call site keeps the feedback its screen already gives (toast, button state,
 * field selection, in-dialog notice); all of those channels are accessible —
 * toasts live in the `#crm-toasts` aria-live region, the manual-copy dialog
 * moves focus into a selected textarea, and the portal notice sits inside
 * its dialog.
 */
export interface CopyToClipboardOptions {
  /**
   * Try the hidden-textarea `execCommand` fallback when the Clipboard API is
   * missing or refuses. Default true.
   *
   * Pass false only when the caller already owns the fallback: the Member
   * Portal's calendar-subscribe dialog selects its visible address input and
   * tells the member to copy with their keyboard, so a hidden textarea would
   * hide the text the member is meant to see selected.
   */
  execCommandFallback?: boolean;
}

/** The async Clipboard API path: true when the text was copied. */
async function tryClipboardApi(text: string): Promise<boolean> {
  if (typeof navigator.clipboard?.writeText !== "function") {
    return false;
  }
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    return false;
  }
}

/**
 * The `execCommand` fallback for insecure contexts: copy from a hidden
 * textarea. Returns whether `execCommand` reported success.
 */
function tryExecCommand(text: string): boolean {
  const textarea = document.createElement("textarea");
  textarea.value = text;
  textarea.setAttribute("readonly", "");
  textarea.style.position = "absolute";
  textarea.style.left = "-9999px";
  document.body.appendChild(textarea);
  try {
    textarea.select();
    return document.execCommand("copy");
  } catch {
    return false;
  } finally {
    document.body.removeChild(textarea);
  }
}

/**
 * Copy `text` to the clipboard.
 *
 * @returns true when the text was copied, false when every mechanism failed —
 * the caller then shows its own error feedback.
 */
export async function copyText(text: string, options: CopyToClipboardOptions = {}): Promise<boolean> {
  if (await tryClipboardApi(text)) {
    return true;
  }
  if (options.execCommandFallback === false) {
    return false;
  }
  return tryExecCommand(text);
}
