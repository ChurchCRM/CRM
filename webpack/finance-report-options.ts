/**
 * Report option pages: every `<select data-report-select>` becomes a searchable multi-select, and its
 * `data-select-all` / `data-clear-all` buttons (value = the select's id) pick or clear every option.
 */
function enhance(select: HTMLSelectElement): void {
  if (!window.TomSelect) {
    return;
  }
  const picker = new window.TomSelect(select, { plugins: ["remove_button"], maxOptions: null });

  document.querySelectorAll<HTMLElement>(`[data-select-all="${select.id}"]`).forEach((button) => {
    button.addEventListener("click", () => {
      picker.setValue(Array.from(select.options, (option) => option.value));
    });
  });
  document.querySelectorAll<HTMLElement>(`[data-clear-all="${select.id}"]`).forEach((button) => {
    button.addEventListener("click", () => picker.clear());
  });
}

function wire(): void {
  document.querySelectorAll<HTMLSelectElement>("select[data-report-select]").forEach(enhance);
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(wire);
} else {
  wire();
}

export {};
