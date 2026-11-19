/**
 * Person profile page (/people/view/{id}): toolbar actions, properties, family role,
 * giving summary, Mailchimp tab, photo upload and the Apple Maps directions option.
 * PHP hands over window.CRM.personView = { id, name, active, famId, canEdit, photoWidth, photoHeight }.
 */
import { confirmAction, promptAction } from "../common/confirm-action";

const reload = () => window.location.reload();

function initToolbar(page) {
  document.getElementById("printPerson")?.addEventListener("click", () => window.print());

  if (!page.active) {
    document.getElementById("person-deactivated")?.classList.remove("d-none");
  }

  document.getElementById("activateDeactivatePerson")?.addEventListener("click", () => {
    const title = page.active ? i18next.t("Confirm Deactivation") : i18next.t("Confirm Activation");
    const prompt = page.active
      ? i18next.t("Please confirm deactivation of person")
      : i18next.t("Please confirm activation of person");
    const message = `<p class="text-danger">${prompt}: ${window.CRM.escapeHtml(page.name)}</p>`;
    confirmAction(title, message, () => {
      window.CRM.APIRequest({ method: "POST", path: `person/${page.id}/activate/${!page.active}` }).then((data) => {
        if (data.success) reload();
      });
    });
  });

  document.getElementById("edit-role-btn")?.addEventListener("click", (event) => {
    event.preventDefault();
    const { family_role_id: currentRoleId, family_role: currentRole } = event.currentTarget.dataset;
    window.CRM.APIRequest({ path: "persons/roles" }).then((roles) => {
      if (!roles.length) return;
      const options = [{ text: currentRole, value: "" }].concat(
        roles
          .filter((role) => String(role.OptionId) !== String(currentRoleId))
          .map((role) => ({ text: role.OptionName, value: String(role.OptionId) })),
      );
      promptAction(
        i18next.t("Change Role"),
        (roleId) => {
          if (!roleId) return;
          window.CRM.APIRequest({ method: "POST", path: `person/${page.id}/role/${roleId}` }).then((data) => {
            if (data.success) reload();
          });
        },
        { inputType: "select", inputOptions: options },
      );
    });
  });
}

function initProperties(page) {
  const select = document.getElementById("input-person-properties");
  if (select) {
    if (!select.tomselect) new TomSelect(select);
    select.addEventListener("change", () => {
      const promptBox = document.getElementById("prompt-box");
      promptBox.replaceChildren();
      const option = select.selectedOptions[0];
      const prompt = option?.dataset.pro_prompt;
      if (!prompt) return;
      const label = document.createElement("label");
      label.textContent = prompt;
      const textarea = document.createElement("textarea");
      textarea.rows = 3;
      textarea.className = "form-control";
      textarea.name = "PropertyValue";
      textarea.value = option.dataset.pro_value || "";
      promptBox.append(label, textarea);
    });
  }

  document.getElementById("assign-property-btn")?.addEventListener("click", () => {
    const form = new FormData(document.getElementById("assign-property-form"));
    const propertyId = form.get("PropertyId");
    if (!propertyId) return;
    window.CRM.APIRequest({
      method: "POST",
      path: `people/properties/person/${page.id}/${propertyId}`,
      data: JSON.stringify({ value: form.get("PropertyValue") || "" }),
    }).then(reload);
  });

  document.querySelectorAll(".remove-property-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      confirmAction("", i18next.t("Are you sure you want to unassign this property?"), () => {
        window.CRM.APIRequest({
          method: "DELETE",
          path: `people/properties/person/${page.id}/${btn.dataset.property_id}`,
        }).then(reload);
      });
    });
  });

  const volunteerSelect = document.getElementById("input-volunteer-opportunities");
  if (volunteerSelect && !volunteerSelect.tomselect) new TomSelect(volunteerSelect, { plugins: ["remove_button"] });
}

function initGivingSummary(page) {
  if (!document.getElementById("giving-history-card") || page.famId <= 0) return;
  const show = (id) => document.getElementById(id)?.classList.remove("d-none");
  const hide = (id) => document.getElementById(id)?.classList.add("d-none");
  const showEmpty = () => {
    hide("giving-history-loading");
    show("giving-history-empty");
  };

  window.CRM.APIRequest({ path: `payments/family/${page.famId}/list` })
    .then((resp) => {
      const rows = (resp?.data ?? []).filter((row) => row.PledgeOrPayment === "Payment");
      if (!rows.length) return showEmpty();

      const currentFY = window.CRM.currentFY || "";
      const ytdTotal = rows
        .filter((row) => currentFY && row.FormattedFY === currentFY)
        .reduce((sum, row) => sum + (parseFloat(row.Amount) || 0), 0);
      const last = rows.reduce((a, b) => (b.Date > a.Date ? b : a));
      const money = (amount) => window.CRM.currency.format(amount);

      document.getElementById("person-giving-ytd").textContent = money(ytdTotal);
      if (ytdTotal > 0) {
        const badge = document.getElementById("person-ytd-total-badge");
        badge.textContent = `${i18next.t("YTD")} ${money(ytdTotal)}`;
        badge.classList.remove("d-none");
      }
      const amount = money(parseFloat(last.Amount));
      document.getElementById("person-last-gift").textContent = last.Fund
        ? `${amount} (${last.Fund}) — ${last.Date || ""}`
        : `${amount} — ${last.Date || ""}`;

      hide("giving-history-loading");
      show("giving-history-content");
    })
    .catch(showEmpty);
}

function initMailchimp(page) {
  if (!document.getElementById("mailchimp")) return;
  fetch(`${window.CRM.root}/plugins/status/mailchimp`)
    .then((r) => r.json())
    .then((status) => {
      if (!(status.success && status.isActive && status.isConfigured)) return;
      document.getElementById("nav-item-mailchimp-container").classList.remove("d-none");
      document.getElementById("mailchimp").classList.remove("d-none");
      return fetch(`${window.CRM.root}/plugins/mailchimp/api/person/${page.id}`)
        .then((r) => {
          if (!r.ok) throw new Error(String(r.status));
          return r.json();
        })
        .then((emails) => {
          for (const email of emails || []) {
            const cell = document.getElementById(email.emailMD5);
            if (!cell) continue;
            const items = (email.list || [])
              .filter((list) => Number(list.status) !== 404)
              .map((list) => {
                const openRate = ((list.stats?.avg_open_rate || 0) * 100).toFixed(2);
                const li = document.createElement("li");
                li.textContent = `${list.name} (${list.status}) - ${openRate}% ${i18next.t("open rate")}`;
                return li;
              });
            cell.removeAttribute("data-loading");
            if (items.length) cell.replaceChildren(...items);
            else cell.textContent = i18next.t("Not Subscribed");
          }
        })
        .catch(() => {
          document.querySelectorAll("#mailchimp td[data-loading='true']").forEach((cell) => {
            cell.removeAttribute("data-loading");
            cell.innerHTML = `<span class="text-muted">${i18next.t("Unable to load")}</span>`;
          });
        });
    });
}

function initPhoto(page) {
  document.getElementById("deletePhoto")?.addEventListener("click", () => window.CRM.deletePhoto("person", page.id));
  document
    .getElementById("view-larger-image-btn")
    ?.addEventListener("click", () => window.CRM.showPhotoLightbox("person", page.id));

  if (!page.canEdit || typeof window._CRM_createPhotoUploader !== "function") return;
  window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
  window.CRM.photoUploader = window.CRM.createPhotoUploader({
    uploadUrl: `${window.CRM.root}/api/person/${page.id}/photo`,
    maxFileSize: window.CRM.maxUploadSizeBytes,
    photoWidth: page.photoWidth,
    photoHeight: page.photoHeight,
    onComplete: reload,
  });
  document.querySelectorAll("#uploadImageButton, #uploadPhotoAction").forEach((el) =>
    el.addEventListener("click", (e) => {
      e.preventDefault();
      window.CRM.photoUploader.show();
    }),
  );
}

function initAppleMapsOption() {
  const ua = navigator.userAgent || "";
  const isAppleMobile =
    /iPad|iPhone|iPod/.test(ua) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  if (!isAppleMobile) return;
  document
    .querySelectorAll(".directions-provider-toggle, .directions-provider-menu")
    .forEach((el) => el.classList.remove("d-none"));
}

export function initPersonPage() {
  const page = window.CRM.personView;
  if (!page) return;
  initToolbar(page);
  initProperties(page);
  initGivingSummary(page);
  initMailchimp(page);
  initPhoto(page);
  initAppleMapsOption();
}
