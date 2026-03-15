function initializeGroupList() {
  window.CRM.groupsInCart = 0;

  // Fetch groups currently in cart
  $.ajax({
    method: "GET",
    url: `${window.CRM.root}/api/groups/groupsInCart`,
    dataType: "json",
  })
    .done((data) => {
      window.CRM.groupsInCart = data.groupsInCart;
    })
    .fail((xhr, status, error) => {
      console.error("Failed to fetch groups in cart:", error);
      window.CRM.notify(i18next.t("Failed to load cart status."), {
        type: "danger",
        delay: 5000,
      });
    });

  $("#addNewGroup").click((e) => {
    const groupName = $("#groupName").val().trim();

    if (!groupName) {
      $("#groupName").addClass("is-invalid").focus();
      return;
    }
    $("#groupName").removeClass("is-invalid");

    const newGroup = { groupName };

    $.ajax({
      method: "POST",
      url: `${window.CRM.root}/api/groups/`,
      data: JSON.stringify(newGroup),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
    })
      .done((data) => {
        // Redirect to the GroupEditor page for the newly created group
        window.location.href = `${window.CRM.root}/GroupEditor.php?GroupID=${data.Id}`;
      })
      .fail((xhr, status, error) => {
        console.error("Failed to create group:", error);
        window.CRM.notify(i18next.t("Failed to create group. Please try again."), {
          type: "danger",
          delay: 5000,
        });
      });
  });

  const dataTableConfig = {
    autoWidth: false,
    ajax: {
      url: `${window.CRM.root}/api/groups/`,
      type: "GET",
      dataSrc: "",
      error: (xhr, error, thrown) => {
        console.error("Failed to load groups:", thrown);
        window.CRM.notify(i18next.t("Failed to load groups. Please refresh the page."), {
          type: "danger",
          delay: 5000,
        });
      },
    },
    columns: [
      {
        width: "auto",
        title: i18next.t("Group Name"),
        data: "Name",
        render: (data, type, full, meta) => {
          const container = document.createElement("div");

          // Clickable icon for editing
          const editLink = document.createElement("a");
          editLink.href = `${window.CRM.root}/GroupEditor.php?GroupID=${full.Id}`;
          editLink.title = i18next.t("Edit Group");

          const editIcon = document.createElement("i");
          editIcon.className = "fa fa-pen";

          editLink.appendChild(editIcon);

          // Clickable group name for viewing
          const nameLink = document.createElement("a");
          nameLink.href = `${window.CRM.root}/GroupView.php?GroupID=${full.Id}`;
          nameLink.title = i18next.t("View Group");
          nameLink.textContent = data;

          // Add elements to container
          container.appendChild(editLink);
          container.appendChild(document.createTextNode(" "));
          container.appendChild(nameLink);

          return container.outerHTML;
        },
      },
      {
        width: "auto",
        title: i18next.t("Group Type"),
        data: "groupType",
        defaultContent: "",
        searchable: true,
        render: (data, type, full, meta) => {
          return data || i18next.t("Unassigned");
        },
      },
      {
        width: "auto",
        title: i18next.t("Members"),
        data: "memberCount",
        searchable: false,
        defaultContent: "0",
      },
      {
        width: "auto",
        title: i18next.t("Group Cart Status"),
        data: null,
        searchable: false,
        render: (data, type, full, meta) => {
          return `<span class="cartStatusButton" data-groupid="${full.Id}" data-membercount="${full.memberCount}">${i18next.t("Checking Cart Status")}</span>`;
        },
      },
    ],
  };

  $.extend(dataTableConfig, window.CRM.plugin.dataTable);

  $("#groupsTable")
    .DataTable(dataTableConfig)
    .on("draw.dt", () => {
      $(".cartStatusButton").each((index, element) => {
        const $element = $(element);
        const objectID = $element.data("groupid");
        const numberOfMembers = $element.data("membercount");

        const isDisabled = numberOfMembers === 0 ? " disabled" : "";

        if (numberOfMembers === 0) {
          $element.html(`<span class="badge badge-light text-muted">${i18next.t("No members")}</span>`);
        } else if ($.inArray(objectID, window.CRM.groupsInCart) > -1) {
          $element.html(
            `<span class="badge badge-success mr-2"><i class="fa-solid fa-check"></i> ${i18next.t("In Cart")}</span>` +
              `<button class="RemoveFromCart btn btn-sm btn-outline-danger" data-cart-id="${objectID}" data-cart-type="group">` +
              `<i class="fa-solid fa-times"></i> ${i18next.t("Remove all")}</button>`,
          );
        } else {
          $element.html(
            `<button class="AddToCart btn btn-sm btn-primary" data-cart-id="${objectID}" data-cart-type="group">` +
              `<i class="fa-solid fa-cart-plus"></i> ${i18next.t("Add all to cart")}</button>`,
          );
        }
      });
    });
}

// Wait for locales to load before initializing
$(document).ready(function () {
  window.CRM.onLocalesReady(initializeGroupList);
});
