/**
 * Report option pages: every `<select data-report-select>` becomes a searchable multi-select, and its
 * `data-select-all` / `data-clear-all` buttons (value = the select's id) pick or clear every option.
 * A form with `data-preview-url` shows its preview in the `#givingPreviewModal` popup instead of reloading;
 * if the request fails the form submits normally.
 */

interface PreviewSummary {
  families: number;
  gifts: number;
  total: string;
  pdfUrl: string;
  csvUrl: string;
}
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

function showPreview(summary: PreviewSummary): void {
  const modal = document.getElementById("givingPreviewModal");
  if (!modal) {
    return;
  }
  const has = summary.gifts > 0;
  document.getElementById("modalSummary")?.classList.toggle("d-none", !has);
  document.getElementById("modalEmpty")?.classList.toggle("d-none", has);
  const text = (id: string, value: string): void => {
    const el = document.getElementById(id);
    if (el) {
      el.textContent = value;
    }
  };
  text("modalFamilies", String(summary.families));
  text("modalGifts", String(summary.gifts));
  text("modalTotal", summary.total);
  for (const [id, url] of [
    ["modalPdf", summary.pdfUrl],
    ["modalCsv", summary.csvUrl],
  ] as const) {
    const link = document.getElementById(id) as HTMLAnchorElement | null;
    if (link) {
      link.href = url;
      link.classList.toggle("d-none", !has);
    }
  }
  window.bootstrap.Modal.getOrCreateInstance(modal).show();
}

function wirePreview(form: HTMLFormElement): void {
  const previewUrl = form.dataset.previewUrl;
  if (!previewUrl) {
    return;
  }
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const params = new URLSearchParams();
    for (const [key, value] of new FormData(form).entries()) {
      if (typeof value === "string" && key !== "preview") {
        params.append(key, value);
      }
    }
    const button = form.querySelector<HTMLButtonElement>("button[type=submit]");
    if (button) {
      button.disabled = true;
    }
    fetch(`${previewUrl}?${params.toString()}`, { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then((response) => {
        if (!response.ok) {
          throw new Error(String(response.status));
        }
        return response.json() as Promise<PreviewSummary>;
      })
      .then(showPreview)
      .catch(() => form.submit())
      .finally(() => {
        if (button) {
          button.disabled = false;
        }
      });
  });
}

function wire(): void {
  document.querySelectorAll<HTMLSelectElement>("select[data-report-select]").forEach(enhance);
  document.querySelectorAll<HTMLFormElement>("form[data-preview-url]").forEach(wirePreview);
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(wire);
} else {
  wire();
}

export {};
