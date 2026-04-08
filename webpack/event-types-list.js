/**
 * Event Types list page — client-side behavior.
 *
 * Reads `window.CRM.eventTypesList.rootPath` for form action paths.
 * Initializes DataTable on the event types table and wires up the
 * "Create Event" / "Delete" action buttons.
 */

document.addEventListener("DOMContentLoaded", () => {
  const $ = window.$;
  if (!$) return;

  const cfg = window.CRM?.eventTypesList || {};
  const rootPath = cfg.rootPath || window.CRM?.root || "";
  const t = window.i18next ? window.i18next.t.bind(window.i18next) : (s) => s;

  if ($("#eventTypesTable tbody tr").length > 0) {
    $("#eventTypesTable").DataTable(window.CRM.plugin.dataTable);
  }

  // Submit a hidden POST form to a target action
  function postForm(action, fields = {}) {
    const f = document.createElement("form");
    f.method = "POST";
    f.action = action;
    Object.entries(fields).forEach(([name, value]) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = value;
      f.appendChild(input);
    });
    document.body.appendChild(f);
    f.submit();
  }

  // Create event from type
  $(document).on("click", ".create-event-btn", function () {
    const typeId = $(this).data("type-id");
    postForm(`${rootPath}/event/editor`, { EN_tyid: typeId });
  });

  // Delete event type with confirmation
  $(document).on("click", ".delete-type-btn", function () {
    const typeId = $(this).data("type-id");
    window.bootbox.confirm({
      title: t("Delete Event Type"),
      message: t("Deleting this event type will NOT delete existing events. Are you sure?"),
      buttons: {
        confirm: { label: t("Yes"), className: "btn-danger" },
        cancel: { label: t("No"), className: "btn-default" },
      },
      callback: (result) => {
        if (result) {
          postForm(`${rootPath}/event/types/${typeId}/delete`);
        }
      },
    });
  });
});
