import "./family-verify.css";

document.addEventListener("DOMContentLoaded", () => {
  const verifyBtn = document.getElementById("onlineVerifyBtn");
  const cancelBtn = document.getElementById("onlineVerifyCancelBtn");
  const siteBtn = document.getElementById("onlineVerifySiteBtn");
  const collectSection = document.getElementById("confirm-modal-collect");
  const doneSection = document.getElementById("confirm-modal-done");
  const errorSection = document.getElementById("confirm-modal-error");

  if (verifyBtn && typeof window.token !== "undefined") {
    verifyBtn.addEventListener("click", () => {
      const verifyType = document.querySelector('input[name="verifyType"]:checked');
      const textarea = document.getElementById("confirm-info-data");

      let message = "";
      if (verifyType && verifyType.value === "no-change") {
        message = "No Changes";
      } else if (textarea?.value.trim()) {
        message = textarea.value.trim();
      } else {
        message = "Changes requested (no details provided)";
      }

      const root = window.CRM ? window.CRM.root : "";

      fetch(`${root}/external/verify/${window.token}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message }),
      })
        .then((resp) => {
          if (resp.ok) {
            if (collectSection) collectSection.classList.add("d-none");
            if (doneSection) doneSection.classList.remove("d-none");
            if (errorSection) errorSection.classList.add("d-none");
            if (verifyBtn) verifyBtn.classList.add("d-none");
            if (cancelBtn) cancelBtn.classList.add("d-none");
            if (siteBtn) siteBtn.classList.remove("d-none");
          } else {
            throw new Error("Verification failed");
          }
        })
        .catch(() => {
          if (collectSection) collectSection.classList.add("d-none");
          if (doneSection) doneSection.classList.add("d-none");
          if (errorSection) errorSection.classList.remove("d-none");
        });
    });
  }
});
