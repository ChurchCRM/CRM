/** Messages may contain trusted HTML; escape interpolated user data. */
export function confirmAction(title: string, message: string, onConfirm: () => void, danger = true): void {
  window.bootbox?.confirm({
    title,
    message,
    buttons: {
      confirm: { label: i18next.t("Yes"), className: danger ? "btn-danger" : "btn-primary" },
      cancel: { label: i18next.t("No"), className: "btn-secondary" },
    },
    callback: (result: boolean) => {
      if (result) onConfirm();
    },
  });
}
