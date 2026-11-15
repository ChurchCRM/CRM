export interface InlineErrorOptions {
  /** Alert container class names. Defaults to 'alert alert-danger mb-0' */
  className?: string;
  /** Icon class names. Defaults to 'fa-solid fa-triangle-exclamation me-1'. Set to false or empty string to omit. */
  icon?: string | false;
}

/**
 * Renders a standardized inline error banner into a container element using safe DOM methods.
 * If message is empty or falsy, the container is cleared.
 */
export function renderInlineError(
  container: HTMLElement | null | undefined,
  message: string,
  options: InlineErrorOptions = {},
): void {
  if (!container) return;
  if (!message) {
    container.textContent = "";
    return;
  }

  const alert = document.createElement("div");
  alert.className = options.className ?? "alert alert-danger mb-0";
  alert.setAttribute("role", "alert");

  const iconClass = options.icon !== undefined ? options.icon : "fa-solid fa-triangle-exclamation me-1";
  if (iconClass) {
    const icon = document.createElement("i");
    icon.className = iconClass;
    alert.appendChild(icon);
  }

  alert.appendChild(document.createTextNode(message));

  if (typeof container.replaceChildren === "function") {
    container.replaceChildren(alert);
  } else {
    container.textContent = "";
    container.appendChild(alert);
  }
}

export function clearInlineError(container: HTMLElement | null | undefined): void {
  if (!container) return;
  container.textContent = "";
}
