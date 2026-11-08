export interface ConfirmOptions {
  danger?: boolean;
  confirmLabel?: string;
  cancelLabel?: string;
  /** Runs on No, Escape, or closing the dialog. */
  onCancel?: () => void;
  className?: string;
}

/** Messages and labels may contain trusted HTML; escape interpolated user data. */
export function confirmAction(
  title: string,
  message: string,
  onConfirm: () => void,
  options: boolean | ConfirmOptions = true,
): void {
  const {
    danger = true,
    confirmLabel,
    cancelLabel,
    onCancel,
    className,
  } = typeof options === "boolean" ? { danger: options } : options;
  window.bootbox?.confirm({
    title: title || undefined,
    message,
    className,
    buttons: {
      confirm: { label: confirmLabel ?? i18next.t("Yes"), className: danger ? "btn-danger" : "btn-primary" },
      cancel: { label: cancelLabel ?? i18next.t("No"), className: "btn-secondary" },
    },
    callback: (result: boolean) => {
      if (result) onConfirm();
      else onCancel?.();
    },
  });
}

export interface AlertOptions {
  className?: string;
  backdrop?: boolean;
  onClose?: () => void;
}

/** A dialog that must stay until dismissed (credentials, long help). Short errors use `window.CRM.notify`. */
export function alertAction(title: string, message: string, options: AlertOptions = {}): void {
  window.bootbox?.alert({
    title: title || undefined,
    message,
    className: options.className,
    backdrop: options.backdrop,
    callback: options.onClose,
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
    callback: (value: string | null) => {
      if (value !== null) onSubmit(value);
    },
  });
}
