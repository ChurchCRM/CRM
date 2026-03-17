/**
 * Sunday School Dashboard — webpack entry
 * Handles DataTable initialization and add-new-class AJAX.
 */

import { buildAPIUrl } from "./api-utils";

document.addEventListener("DOMContentLoaded", () => {
  $(".data-table").DataTable(window.CRM.plugin.dataTable);

  const addBtn = document.getElementById("addNewClassBtn");
  const nameInput = /** @type {HTMLInputElement|null} */ (document.getElementById("new-class-name"));

  if (addBtn && nameInput) {
    addBtn.addEventListener("click", async () => {
      const groupName = nameInput.value.trim();
      if (!groupName) return;

      try {
        const response = await fetch(buildAPIUrl("groups/"), {
          method: "POST",
          headers: { "Content-Type": "application/json; charset=utf-8" },
          body: JSON.stringify({ groupName, isSundaySchool: true }),
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        window.location.href = `${window.CRM.root}/groups/sundayschool/class/${data.Id}`;
      } catch (error) {
        console.error("Failed to create Sunday School class:", error);
      }
    });
  }
});
