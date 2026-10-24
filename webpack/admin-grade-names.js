/**
 * Admin → Grades Editor: switch the grade list to another country's names (#10189).
 * The server previews the change first; the admin confirms before anything is saved.
 */

function html(text) {
  return window.CRM.escapeHtml(String(text));
}

function previewHtml(preview) {
  const lines = [];
  if (preview.renamed.length > 0) {
    lines.push(
      `<p>${html(i18next.t("Grades renamed: {{total}}", { total: preview.renamed.length }))}</p>`,
      `<ul class="small">${preview.renamed.map((r) => `<li>${html(r.from)} → ${html(r.to)}</li>`).join("")}</ul>`,
    );
  }
  if (preview.added.length > 0) {
    lines.push(`<p>${html(i18next.t("Grades added"))}: ${preview.added.map(html).join(", ")}</p>`);
  }
  if (preview.merged.length > 0) {
    lines.push(
      `<p>${html(i18next.t("Grades you added that match a name in the list and are combined with it"))}: ${preview.merged
        .map((m) => html(m.name))
        .join(", ")}</p>`,
    );
  }
  if (preview.removed.length > 0) {
    lines.push(
      `<p class="text-danger">${html(i18next.t("Grades removed"))}: ${preview.removed
        .map((r) => html(r.name))
        .join(", ")}</p>`,
      `<p class="text-danger">${html(
        i18next.t("People whose grade is cleared: {{total}}", { total: preview.cleared }),
      )}</p>`,
    );
  }
  return lines.join("");
}

function apply(key, button) {
  button.disabled = true;
  window.CRM.AdminAPIRequest({ method: "GET", path: `grades/presets/${encodeURIComponent(key)}/preview` })
    .done((preview) => {
      const changes = previewHtml(preview);
      if (changes === "") {
        button.disabled = false;
        window.CRM.notify(i18next.t("The grades already use these names."), { type: "info" });
        return;
      }
      window.bootbox.confirm({
        title: i18next.t("Change Grade Names"),
        message: changes,
        buttons: {
          cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
          confirm: { label: i18next.t("Apply"), className: "btn-primary" },
        },
        callback: (confirmed) => {
          if (!confirmed) {
            button.disabled = false;
            return;
          }
          window.CRM.AdminAPIRequest({
            method: "POST",
            path: `grades/presets/${encodeURIComponent(key)}`,
            data: JSON.stringify({ expectedState: preview.state }),
            headers: { "X-CSRF-Token": button.dataset.csrfToken },
          })
            .done(() => window.location.reload())
            .fail(() => {
              button.disabled = false;
            });
        },
      });
    })
    .fail(() => {
      button.disabled = false;
    });
}

document.addEventListener("DOMContentLoaded", () => {
  const button = document.getElementById("applyGradePreset");
  const select = document.getElementById("gradePreset");
  if (button instanceof HTMLButtonElement && select instanceof HTMLSelectElement) {
    button.addEventListener("click", () => apply(select.value, button));
  }
});
