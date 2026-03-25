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
          const nameLink = document.createElement("a");
          nameLink.href = `${window.CRM.root}/GroupView.php?GroupID=${full.Id}`;
          nameLink.textContent = data;
          return nameLink.outerHTML;
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
        width: "1",
        title: i18next.t("Actions"),
        data: null,
        orderable: false,
        searchable: false,
        className: "text-end w-1 no-export",
        render: (data, type, full, meta) => {
          const inCart = $.inArray(full.Id, window.CRM.groupsInCart) > -1;
          const hasMembers = full.memberCount > 0;
          const cartBtn = hasMembers
            ? (inCart
              ? `<button class="dropdown-item text-danger RemoveFromCart" data-cart-id="${full.Id}" data-cart-type="group" data-label-add="${i18next.t("Add all to Cart")}" data-label-remove="${i18next.t("Remove all from Cart")}"><i class="ti ti-shopping-cart-off me-2"></i><span class="cart-label">${i18next.t("Remove from Cart")}</span></button>`
              : `<button class="dropdown-item AddToCart" data-cart-id="${full.Id}" data-cart-type="group" data-label-add="${i18next.t("Add all to Cart")}" data-label-remove="${i18next.t("Remove all from Cart")}"><i class="ti ti-shopping-cart-plus me-2"></i><span class="cart-label">${i18next.t("Add all to Cart")}</span></button>`)
            : "";
          return (
            '<div class="dropdown">' +
            '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">' +
            '<i class="ti ti-dots-vertical"></i>' +
            "</button>" +
            '<div class="dropdown-menu dropdown-menu-end">' +
            `<a class="dropdown-item" href="${window.CRM.root}/GroupView.php?GroupID=${full.Id}"><i class="ti ti-eye me-2"></i>${i18next.t("View")}</a>` +
            `<a class="dropdown-item" href="${window.CRM.root}/GroupEditor.php?GroupID=${full.Id}"><i class="ti ti-pencil me-2"></i>${i18next.t("Edit")}</a>` +
            (hasMembers ? '<div class="dropdown-divider"></div>' + cartBtn : "") +
            "</div></div>"
          );
        },
      },
    ],
  };

  $.extend(dataTableConfig, window.CRM.plugin.dataTable);

  $("#groupsTable").DataTable(dataTableConfig);
}

// Wait for locales to load before initializing
$(document).ready(function () {
  window.CRM.onLocalesReady(initializeGroupList);
});
