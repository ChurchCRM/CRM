// Pages the rows of any tbody marked data-row-pager by the user's "Rows per page" setting.
function pageRows(tbody: HTMLElement): void {
  const size = Number(window.CRM?.plugin?.dataTable?.pageLength ?? 10);
  const rows = Array.from(tbody.children) as HTMLElement[];
  if (size <= 0 || rows.length <= size) {
    return;
  }
  const pages = Math.ceil(rows.length / size);
  let page = 0;

  const bar = document.createElement("div");
  bar.className = "card-footer d-flex align-items-center justify-content-end gap-2 py-2";
  bar.innerHTML =
    `<span class="small text-body-secondary me-2"></span>` +
    `<button type="button" class="btn btn-sm btn-ghost-secondary" data-dir="-1" aria-label="${i18next.t("Previous")}"><i class="fa-solid fa-chevron-left"></i></button>` +
    `<button type="button" class="btn btn-sm btn-ghost-secondary" data-dir="1" aria-label="${i18next.t("Next")}"><i class="fa-solid fa-chevron-right"></i></button>`;
  tbody.closest(".card")?.appendChild(bar);

  const render = () => {
    rows.forEach((row, i) => row.classList.toggle("d-none", Math.floor(i / size) !== page));
    (bar.querySelector("span") as HTMLElement).textContent =
      `${page * size + 1}–${Math.min((page + 1) * size, rows.length)} / ${rows.length}`;
    (bar.querySelector('[data-dir="-1"]') as HTMLButtonElement).disabled = page === 0;
    (bar.querySelector('[data-dir="1"]') as HTMLButtonElement).disabled = page === pages - 1;
  };
  bar.addEventListener("click", (event) => {
    const button = (event.target as Element).closest<HTMLButtonElement>("[data-dir]");
    if (button) {
      page = Math.min(pages - 1, Math.max(0, page + Number(button.dataset.dir)));
      render();
    }
  });
  render();
}

window.addEventListener("DOMContentLoaded", () => {
  window.CRM?.onLocalesReady?.(() => document.querySelectorAll<HTMLElement>("tbody[data-row-pager]").forEach(pageRows));
});
