/**
 * Report option pages: every `<select data-report-select>` becomes a searchable multi-select, and its
 * `data-select-all` / `data-clear-all` buttons (value = the select's id) pick or clear every option.
 * A form with `data-preview-url` posts its filters there and shows the summary in the `#givingPreviewModal`
 * popup instead of reloading; the popup's own form posts the same filters to download the PDF or CSV, so a long
 * family selection never lands in a URL. If the request fails the form submits normally.
 */

interface PreviewSummary {
  families: number;
  gifts: number;
  total: string;
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

function showPreview(summary: PreviewSummary, filters: URLSearchParams): void {
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
  const download = document.getElementById("modalDownload");
  if (download) {
    download.classList.toggle("d-none", !has);
    download.querySelectorAll("input[data-filter]").forEach((input) => input.remove());
    filters.forEach((value, key) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = key;
      input.value = value;
      input.dataset.filter = "1";
      download.prepend(input);
    });
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
    const filters = new URLSearchParams();
    for (const [key, value] of new FormData(form).entries()) {
      if (typeof value === "string" && key !== "preview") {
        filters.append(key, value);
      }
    }
    const button = form.querySelector<HTMLButtonElement>("button[type=submit]");
    if (button) {
      button.disabled = true;
    }
    fetch(previewUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json", "Content-Type": "application/x-www-form-urlencoded" },
      body: filters.toString(),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(String(response.status));
        }
        return response.json() as Promise<PreviewSummary>;
      })
      .then((summary) => showPreview(summary, filters))
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
