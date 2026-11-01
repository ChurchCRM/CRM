$(document).ready(() => {
  // Initialize TomSelect on the person picker if present
  const personSelectEl = document.getElementById("personSelect");
  if (personSelectEl && !personSelectEl.tomselect) {
    new TomSelect(personSelectEl);
  }

  // Access level is one of two modes:
  //   admin  — Admin flag only; all module perms irrelevant
  //   custom — Admin=0; module perms visible & editable
  const modulePerms = [
    "AddRecords",
    "EditRecords",
    "DeleteRecords",
    "ManageGroups",
    "Finance",
    "Notes",
    "ManageFundraisers",
    "AddEvent",
  ];
  const adminCb = document.getElementById("Admin");
  const customBlock = document.getElementById("customPermissions");
  const pfPanel = document.getElementById("pfPanel");
  const modeRadios = document.querySelectorAll('input[name="accessMode"]');

  const applyMode = (mode, clearModules) => {
    if (customBlock) {
      customBlock.style.display = mode === "custom" ? "" : "none";
    }
    if (pfPanel) {
      pfPanel.style.display = mode === "custom" ? "" : "none";
    }
    if (adminCb) {
      adminCb.checked = mode === "admin";
    }
    if (mode !== "custom" && clearModules) {
      modulePerms.forEach((name) => {
        const el = document.getElementById(name);
        if (el) {
          el.checked = false;
        }
      });
    }
  };

  modeRadios.forEach((radio) => {
    // Cannot use arrow function here — `this` refers to the radio element
    radio.addEventListener("change", function () {
      if (this.checked) {
        applyMode(this.value, true);
      }
    });
  });

  // On page load: sync visibility only — preserve stored module switch states.
  const initial = document.querySelector('input[name="accessMode"]:checked');
  if (initial) {
    applyMode(initial.value, false);
  }
});
