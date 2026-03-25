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

  // Add role members (Teachers or Students) to cart
  $(document).on("click", ".add-ss-role-to-cart", async function () {
    const groupId = $(this).data("group-id");
    const roleName = $(this).data("role-name");

    try {
      const [rolesRes, membersRes] = await Promise.all([
        fetch(`${window.CRM.root}/api/groups/${groupId}/roles`),
        fetch(`${window.CRM.root}/api/groups/${groupId}/members`),
      ]);

      if (!rolesRes.ok || !membersRes.ok) throw new Error("Failed to fetch group data");

      const roles = await rolesRes.json();
      const membersData = await membersRes.json();

      const foundRole = roles.find((r) => r.OptionName === roleName);
      if (!foundRole) {
        window.CRM.notify(i18next.t("Role not found."), { type: "danger", delay: 5000 });
        return;
      }

      const members = membersData.Person2group2roleP2g2rs || [];
      const personIds = members
        .filter((m) => m.RoleId === foundRole.OptionId)
        .map((m) => m.PersonId);

      if (personIds.length === 0) {
        window.CRM.notify(i18next.t("No members found for this role."), { type: "warning", delay: 3000 });
        return;
      }

      const cartRes = await fetch(`${window.CRM.root}/api/cart/`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ Persons: personIds }),
      });

      if (!cartRes.ok) throw new Error(`HTTP ${cartRes.status}`);

      window.CRM.notify(i18next.t("Members added to cart."), { type: "success", delay: 3000 });
    } catch (error) {
      console.error("Failed to add members to cart:", error);
      window.CRM.notify(i18next.t("Failed to add members to cart. Please try again."), { type: "danger", delay: 5000 });
    }
  });

  // Delete Sunday School class
  $(document).on("click", ".delete-ss-class", function () {
    const groupId = $(this).data("group-id");
    const groupName = $(this).data("group-name");

    bootbox.confirm({
      message: i18next.t("Are you sure you want to delete") + " <strong>" + groupName + "</strong>?",
      buttons: {
        confirm: { label: i18next.t("Delete"), className: "btn-danger" },
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
      },
      callback: (result) => {
        if (!result) return;

        fetch(`${window.CRM.root}/api/groups/${groupId}`, { method: "DELETE" })
          .then((res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            window.location.reload();
          })
          .catch((error) => {
            console.error("Failed to delete class:", error);
            window.CRM.notify(i18next.t("Failed to delete class. Please try again."), { type: "danger", delay: 5000 });
          });
      },
    });
  });
});
