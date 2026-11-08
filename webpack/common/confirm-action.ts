export interface ConfirmOptions {
  /** Confirm button colour; the boolean shorthand `true`/`false` means danger/primary. */
  tone?: "danger" | "warning" | "primary";
  confirmLabel?: string;
  cancelLabel?: string;
  /** Runs on No, Escape, or closing the dialog. */
  onCancel?: () => void;
  className?: string;
}

/**
 * Bootstrap ignores hide() while a modal is still fading in, so an answer given
 * that early would leave the dialog open. Answer once, and close when shown.
 */
function answerOnce<T extends unknown[]>(handle: (...args: T) => void, whenShown?: (modal: Element) => void) {
  let shown = false;
  let answered = false;
  let closeWhenShown = false;
  return {
    onShown: (event: { target: EventTarget | null }) => {
      shown = true;
      whenShown?.(event.target as Element);
      if (closeWhenShown) window.bootstrap.Modal.getOrCreateInstance(event.target as Element).hide();
    },
    callback: (...args: T): boolean => {
      if (answered) return false;
      answered = true;
      handle(...args);
      if (shown) return true;
      closeWhenShown = true;
      return false;
    },
  };
}

/** Messages and labels may contain trusted HTML; escape interpolated user data. */
export function confirmAction(
  title: string,
  message: string,
  onConfirm: () => void,
  options: boolean | ConfirmOptions = true,
): void {
  const {
    tone = "danger",
    confirmLabel,
    cancelLabel,
    onCancel,
    className,
  } = typeof options === "boolean" ? { tone: options ? "danger" : "primary" } : options;
  window.bootbox?.confirm({
    title: title || undefined,
    message,
    className,
    buttons: {
      confirm: { label: confirmLabel ?? i18next.t("Yes"), className: `btn-${tone}` },
      cancel: { label: cancelLabel ?? i18next.t("No"), className: "btn-secondary" },
    },
    ...answerOnce((result: boolean) => {
      if (result) onConfirm();
      else onCancel?.();
    }),
  });
}

export interface AlertOptions {
  className?: string;
  backdrop?: boolean;
  onClose?: () => void;
  onShown?: (modal: Element) => void;
}

/** A dialog that must stay until dismissed (credentials, long help). Short errors use `window.CRM.notify`. */
export function alertAction(title: string, message: string, options: AlertOptions = {}): void {
  window.bootbox?.alert({
    title: title || undefined,
    message,
    className: options.className,
    backdrop: options.backdrop,
    ...answerOnce(() => options.onClose?.(), options.onShown),
  });
}

export interface PromptOptions {
  message?: string;
  value?: string;
  inputType?: "text" | "select" | "textarea" | "number";
  inputOptions?: { text: string; value: string }[];
  confirmLabel?: string;
  cancelLabel?: string;
}

/** Asks for one value. `onSubmit` runs only on confirm, with the value as typed (possibly empty). */
export function promptAction(title: string, onSubmit: (value: string) => void, options: PromptOptions = {}): void {
  const { confirmLabel, cancelLabel, ...rest } = options;
  window.bootbox?.prompt({
    title,
    ...rest,
    buttons: {
      confirm: { label: confirmLabel ?? i18next.t("OK"), className: "btn-primary" },
      cancel: { label: cancelLabel ?? i18next.t("Cancel"), className: "btn-secondary" },
    },
    ...answerOnce((value: string | null) => {
      if (value !== null) onSubmit(value);
    }),
  });
}
