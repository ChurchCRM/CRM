import { confirmAction } from "./common/confirm-action";

window.CRM.onLocalesReady(() => {
  const ROOT = window.CRM.root;
  const GROUP_KEY = window.CRM.pledgeEditor.groupKey;
  const PLEDGE_TYPE = window.CRM.pledgeEditor.type;
  const LINK_BACK_RAW = window.CRM.pledgeEditor.linkBack;
  const LINK_BACK_TARGET = window.CRM.pledgeEditor.linkBackTarget;
  const REQUIRE_CHECK_NO = window.CRM.pledgeEditor.requireCheckNumber;

  // ---- Family TomSelect ----
  const familyNameEl = document.getElementById("FamilyName");
  let latestQuery = "";
  if (familyNameEl && !familyNameEl.tomselect) {
    new TomSelect(familyNameEl, {
      valueField: "id",
      labelField: "text",
      searchField: [],
      load: function (query, callback) {
        this.clearOptions();
        latestQuery = query;
        if (query.length < 2 && !/^\d+$/.test(query)) return callback();
        fetch(ROOT + "/api/families/search/" + encodeURIComponent(query))
          .then((res) => res.json())
          .then((data) => {
            if (query !== latestQuery) return callback();
            var families = data && data.Families ? data.Families : [];
            callback(families.map((obj) => ({ id: String(obj.Id), text: obj.displayName })));
          })
          .catch(() => {
            callback();
          });
      },
      onChange: (value) => {
        document.getElementById("FamilyID").value = value;
      },
    });
    if (!GROUP_KEY && !familyNameEl.value) {
      familyNameEl.tomselect.focus();
    }
  }

  // ---- Fund total calculation ----
  function recalcTotal() {
    let total = 0;
    document.querySelectorAll(".fund-amount").forEach((input) => {
      const v = parseFloat(input.value);
      if (!isNaN(v) && v > 0) total += v;
    });
    document.getElementById("fundTotal").textContent = total.toFixed(2);
  }

  document.addEventListener("input", (e) => {
    if (e.target.classList.contains("fund-amount")) recalcTotal();
  });

  // ---- Add fund row ----
  document.getElementById("addFundRow").addEventListener("click", () => {
    const tmpl = document.getElementById("fundRowTemplate");
    const clone = tmpl.content.cloneNode(true);
    document.getElementById("fundRows").appendChild(clone);
    recalcTotal();
  });

  // ---- Remove fund row ----
  document.getElementById("fundRows").addEventListener("click", (e) => {
    const btn = e.target.closest(".remove-fund-row");
    if (!btn) return;
    const rows = document.querySelectorAll(".fund-row");
    if (rows.length <= 1) {
      window.CRM.notify(window.CRM.escapeHtml(i18next.t("At least one fund allocation is required")), {
        type: "danger",
        delay: 4000,
      });
      return;
    }
    btn.closest("tr").remove();
    recalcTotal();
  });

  // ---- Payment method toggle for check # field ----
  const methodEl = document.getElementById("Method");
  const checkGroup = document.getElementById("checkNumberGroup");
  const checkEl = document.getElementById("CheckNo");
  if (methodEl && checkGroup && checkEl) {
    function toggleCheckGroup() {
      if (methodEl.value === "CHECK") {
        checkGroup.style.display = "";
        if (REQUIRE_CHECK_NO) {
          checkEl.required = true;
        }
      } else {
        checkGroup.style.display = "none";
        checkEl.value = "";
        checkEl.required = false;
      }
    }
    methodEl.addEventListener("change", toggleCheckGroup);
    toggleCheckGroup();
  }

  // ---- Date → auto-resolve FYID via FiscalYearUtils (/api/fiscalyear) ----
  const dateEl = document.getElementById("Date");
  const fyEl = document.getElementById("FYID");

  if (dateEl && fyEl) {
    dateEl.addEventListener("change", async function () {
      if (!this.value) {
        return;
      }
      const res = await fetch(ROOT + "/api/fiscalyear?date=" + encodeURIComponent(this.value));
      if (!res.ok) {
        return;
      }
      const data = await res.json();
      if (data.fyId) {
        fyEl.value = String(data.fyId);
      }
    });
  }

  // ---- Collect form data for API submission ----
  function collectPayload() {
    const familyId = parseInt(document.getElementById("FamilyID").value, 10);
    const date = document.getElementById("Date").value;
    const fyid = parseInt(fyEl ? fyEl.value : "0", 10);
    const methodEl2 = document.getElementById("Method");
    // Pledges have no Method dropdown; default to CASH so generateGroupKey never receives null iCheckNo
    const method = methodEl2 ? methodEl2.value : PLEDGE_TYPE === "Pledge" ? "CASH" : "CHECK";
    const checkEl = document.getElementById("CheckNo");
    const checkNo = checkEl ? checkEl.value : "";
    const depEl = document.getElementById("DepositID");
    const depId = depEl ? parseInt(depEl.value, 10) : 0;
    const schedEl = document.getElementById("Schedule");
    const schedule = schedEl ? schedEl.value : "Once";

    if (!date) {
      window.CRM.notify(window.CRM.escapeHtml(i18next.t("Please enter a date")), { type: "danger", delay: 4000 });
      return null;
    }
    if (REQUIRE_CHECK_NO && method === "CHECK" && (!checkNo || checkNo === "0")) {
      window.CRM.notify(window.CRM.escapeHtml(i18next.t("Please enter a check number")), {
        type: "danger",
        delay: 4000,
      });
      return null;
    }

    // Build FundSplit array
    const fundRows = document.querySelectorAll(".fund-row");
    const fundSplit = [];
    let hasAmount = false;
    let validationError = null;

    fundRows.forEach((row) => {
      const fundSelect = row.querySelector(".fund-select");
      const amountInput = row.querySelector(".fund-amount");
      const ndInput = row.querySelector(".fund-nondeductible");
      const commentInput = row.querySelector(".fund-comment");

      const fundId = fundSelect ? fundSelect.value : "";
      const amount = parseFloat(amountInput ? amountInput.value : "0") || 0;
      const nd = parseFloat(ndInput ? ndInput.value : "0") || 0;
      const comment = commentInput ? commentInput.value : "";

      if (!fundId || fundId === "") {
        validationError = i18next.t("Please select a fund for all rows");
        return;
      }
      if (amount > 999999.99) {
        validationError = i18next.t("Amount exceeds maximum allowed (999999.99)");
        return;
      }
      if (nd > amount) {
        validationError = i18next.t("Non-deductible amount can't exceed fund amount");
        return;
      }
      if (amount > 0) hasAmount = true;

      fundSplit.push({
        FundID: fundId,
        Amount: amount,
        NonDeductible: nd,
        Comment: comment,
      });
    });

    if (validationError) {
      window.CRM.notify(window.CRM.escapeHtml(validationError), { type: "danger", delay: 4000 });
      return null;
    }
    if (!hasAmount) {
      window.CRM.notify(window.CRM.escapeHtml(i18next.t("At least one fund must have a non-zero amount.")), {
        type: "danger",
        delay: 4000,
      });
      return null;
    }

    return {
      FamilyID: familyId || null,
      Date: date,
      FYID: fyid,
      type: PLEDGE_TYPE,
      iMethod: method,
      iCheckNo: checkNo || null,
      DepositID: depId || null,
      schedule: schedule,
      FundSplit: JSON.stringify(fundSplit),
      tScanString: "",
    };
  }

  // ---- Save (POST /api/payments/pledges) ----
  const actionButtons = document.querySelectorAll("#savePledgeBtn, #saveAndAddBtn, #deletePledgeBtn");
  let saving = false;

  function setSaving(value) {
    saving = value;
    actionButtons.forEach((btn) => {
      btn.disabled = value;
    });
  }

  async function savePledge(redirectAfter) {
    if (saving) return;
    const payload = collectPayload();
    if (!payload) return;

    setSaving(true);
    try {
      const isEditMode = !!GROUP_KEY;
      const url = isEditMode ? ROOT + "/api/payments/" + encodeURIComponent(GROUP_KEY) : ROOT + "/api/payments/pledges";
      const res = await fetch(url, {
        method: isEditMode ? "PUT" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        const msg = (data && (data.error || data.message)) || i18next.t("Save failed");
        window.CRM.notify(window.CRM.escapeHtml(msg), { type: "danger", delay: 4000 });
        setSaving(false);
        return;
      }

      window.CRM.notify(window.CRM.escapeHtml(i18next.t("Saved successfully")), { type: "success", delay: 4000 });

      if (redirectAfter === "new") {
        setTimeout(() => {
          var newUrl = ROOT + "/finance/pledge/new?type=" + encodeURIComponent(PLEDGE_TYPE);
          var depEl = document.getElementById("DepositID");
          var currentDepId = depEl ? parseInt(depEl.value, 10) : 0;
          if (currentDepId) newUrl += "&depositId=" + encodeURIComponent(currentDepId);
          if (LINK_BACK_RAW) newUrl += "&linkBack=" + encodeURIComponent(LINK_BACK_RAW);
          window.location.href = newUrl;
        }, 800);
      } else if (LINK_BACK_TARGET) {
        setTimeout(() => {
          window.location.href = LINK_BACK_TARGET;
        }, 800);
      } else if (data && data.groupKey) {
        setTimeout(() => {
          window.location.href = ROOT + "/finance/pledge/" + encodeURIComponent(data.groupKey);
        }, 800);
      } else {
        setTimeout(() => {
          window.location.href = ROOT + "/finance/";
        }, 800);
      }
    } catch (err) {
      window.CRM.notify(window.CRM.escapeHtml(i18next.t("Network error, please try again")), {
        type: "danger",
        delay: 4000,
      });
      setSaving(false);
    }
  }

  document.getElementById("savePledgeBtn").addEventListener("click", () => {
    savePledge("view");
  });

  const saveAndAddBtn = document.getElementById("saveAndAddBtn");
  if (saveAndAddBtn) {
    saveAndAddBtn.addEventListener("click", () => {
      savePledge("new");
    });
  }

  // ---- Ctrl+Enter / Cmd+Enter: Save and Add Another (Save when editing) ----
  if (window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
    const hint = document.getElementById("saveShortcutHint");
    if (/Mac/.test(navigator.userAgent)) {
      hint.querySelector(".shortcut-mod").textContent = "⌘";
    }
    hint.classList.remove("d-none");
  }

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Enter" || !(e.ctrlKey || e.metaKey) || e.isComposing) return;
    e.preventDefault();
    if (document.querySelector(".bootbox.show")) return;
    savePledge(saveAndAddBtn ? "new" : "view");
  });

  // ---- Delete ----
  const deleteBtn = document.getElementById("deletePledgeBtn");
  if (deleteBtn && GROUP_KEY) {
    deleteBtn.addEventListener("click", () => {
      const confirmMsg =
        PLEDGE_TYPE === "Pledge"
          ? i18next.t("Are you sure you want to permanently delete this pledge record?")
          : i18next.t("Are you sure you want to permanently delete this payment record?");
      confirmAction(i18next.t("Delete"), confirmMsg, async () => {
        try {
          const res = await fetch(ROOT + "/api/payments/" + encodeURIComponent(GROUP_KEY), {
            method: "DELETE",
          });
          if (res.ok) {
            window.CRM.notify(window.CRM.escapeHtml(i18next.t("Deleted successfully")), {
              type: "success",
              delay: 4000,
            });
            setTimeout(() => {
              window.location.href = LINK_BACK_TARGET || ROOT + "/finance/";
            }, 800);
          } else {
            const data = await res.json().catch(() => ({}));
            const msg = (data && (data.error || data.message)) || i18next.t("Delete failed");
            window.CRM.notify(window.CRM.escapeHtml(msg), { type: "danger", delay: 4000 });
          }
        } catch (err) {
          window.CRM.notify(window.CRM.escapeHtml(i18next.t("Network error, please try again")), {
            type: "danger",
            delay: 4000,
          });
        }
      });
    });
  }

  // Initial total calculation
  recalcTotal();
});
