export function notifyError(message: string): void {
  window.CRM?.notify?.(message, { type: "danger" });
}

export function notifySuccess(message: string): void {
  window.CRM?.notify?.(message, { type: "success" });
}

/** "Nothing broke, but nothing happened either", an existing grant for example. */
export function notifyWarning(message: string): void {
  window.CRM?.notify?.(message, { type: "warning" });
}
