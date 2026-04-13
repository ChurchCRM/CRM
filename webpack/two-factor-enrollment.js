/**
 * Two-Factor Authentication Enrollment
 *
 * Multi-step wizard: loading → intro → scan QR → success / status-enabled.
 * Uses Bootstrap 5 cards and vanilla fetch against the 2FA API.
 */

const CRMRoot = window.CRM.root;
const t = (key) => (window.i18next ? window.i18next.t(key) : key);

function fetchJSON(url, opts = {}) {
  return fetch(url, { credentials: "include", ...opts }).then((r) => {
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    if (r.status === 204) return {};
    return r.json();
  });
}

function escapeHtml(str) {
  if (!str) return "";
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

function formatRecoveryCode(code) {
  // New format: 12 uppercase alphanumeric chars stored without dash — display as XXXXXX-XXXXXX
  if (/^[A-Z2-9]{12}$/i.test(code)) {
    return `${code.slice(0, 6)}-${code.slice(6)}`;
  }
  return code; // legacy base64 — show as-is
}

function notifyError(message) {
  if (window.CRM?.notify) {
    window.CRM.notify(message, { type: "danger" });
  }
}

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

const state = {
  currentView: "loading",
  is2FAEnabled: false,
  initialLoadComplete: false,
  TwoFAQRCodeDataUri: "",
  currentTwoFAPin: "",
  currentTwoFAPinStatus: "",
  TwoFARecoveryCodes: [],
};

function getContainer() {
  return document.getElementById("two-factor-enrollment-app");
}

// ---------------------------------------------------------------------------
// Views
// ---------------------------------------------------------------------------

function renderLoading() {
  return `<div class="row">
    <div class="col-lg-8">
      <div class="card card-outline card-primary">
        <div class="card-body p-5 text-center">
          <span role="status" aria-live="polite" class="d-inline-block">
            <span class="spinner-border" aria-hidden="true"></span>
            <span class="visually-hidden">${t("Loading")}...</span>
          </span>
        </div>
      </div>
    </div>
  </div>`;
}

function renderIntro() {
  return `<div class="row">
    <div class="col-lg-8">
      <div class="card card-outline card-primary">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="fa-solid fa-shield me-2"></i>${t("Enable Two-Factor Authentication")}</h4>
        </div>
        <div class="card-body">
          <p class="text-muted text-center mb-4">${t("Add an extra layer of security to your account")}</p>
          <div class="mb-4">
            <div class="d-flex align-items-start mb-3">
              <span class="badge bg-primary me-3" style="min-width:28px;padding:6px 0">1</span>
              <div><strong>${t("Sign In")}</strong><div class="text-muted small">${t("Enter your username and password as usual")}</div></div>
            </div>
            <div class="d-flex align-items-start mb-3">
              <span class="badge bg-primary me-3" style="min-width:28px;padding:6px 0">2</span>
              <div><strong>${t("One-Time Code")}</strong><div class="text-muted small">${t("Confirm with a code from your authenticator app")}</div></div>
            </div>
            <div class="d-flex align-items-start">
              <span class="badge bg-primary me-3" style="min-width:28px;padding:6px 0">3</span>
              <div><strong>${t("Secure")}</strong><div class="text-muted small">${t("Your account is now protected")}</div></div>
            </div>
          </div>
          <hr>
          <div class="callout callout-warning">
            <h6 class="fw-bold">${t("Before You Start")}</h6>
            <ul class="mb-0 ps-3 small">
              <li>${t("Have your authenticator app ready (Google Authenticator, Microsoft Authenticator, Authy, etc.)")}</li>
              <li>${t("This will replace any previously enrolled 2FA methods")}</li>
              <li>${t("You'll receive backup codes that can be used if you lose access to your app")}</li>
            </ul>
          </div>
          <div class="text-center mt-4">
            <button type="button" id="begin2faEnrollment" class="btn btn-primary btn-lg w-100">
              <i class="fa-solid fa-arrow-right me-2"></i>${t("Get Started")}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function renderQRCode() {
  let statusHtml = "";
  if (state.currentTwoFAPinStatus === "pending") {
    statusHtml = `<div class="text-center text-info small">
      <span class="fa-solid fa-spinner fa-spin me-1" aria-hidden="true"></span>${t("Verifying")}&hellip;
    </div>`;
  } else if (state.currentTwoFAPinStatus === "invalid") {
    statusHtml = `<div class="text-center text-danger small">
      <i class="fa-solid fa-circle-xmark me-1"></i>${t("Code is invalid")} &ndash; ${t("Please try again")}
    </div>`;
  }

  const srStatus =
    state.currentTwoFAPinStatus === "pending"
      ? t("Validation pending")
      : state.currentTwoFAPinStatus === "invalid"
        ? t("Code is invalid")
        : state.currentTwoFAPinStatus === "incomplete"
          ? t("Code incomplete")
          : "";

  return `<div class="row">
    <div class="col-lg-8">
      <div class="card card-outline card-primary">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="fa-solid fa-qrcode me-2"></i>${t("Set Up Authenticator")}</h4>
        </div>
        <div class="card-body">
          <div class="mb-4">
            <h6 class="fw-bold d-flex align-items-center mb-3">
              <span class="badge bg-primary me-2" style="min-width:24px;padding:4px 0">1</span>${t("Scan QR Code")}
            </h6>
            <p class="text-muted small mb-3">${t("Open your authenticator app and scan this QR code")}</p>
            <div class="text-center mb-3">
              <div class="d-inline-block p-3" style="border:2px solid #dee2e6;border-radius:8px;background-color:#fff">
                <img id="2faQrCodeDataUri" src="${state.TwoFAQRCodeDataUri}" alt="2FA QR Code" style="max-width:200px;height:auto;display:block">
              </div>
            </div>
            <p class="text-muted small text-center mb-0">
              ${t("Can't scan?")} <button type="button" class="btn btn-link btn-sm p-0" id="newQRCodeBtn">${t("Generate new code")}</button>
            </p>
          </div>
          <hr>
          <div class="mb-3">
            <h6 class="fw-bold d-flex align-items-center mb-3">
              <span class="badge bg-primary me-2" style="min-width:24px;padding:4px 0">2</span>${t("Verify Code")}
            </h6>
            <p class="text-muted small mb-3">${t("Enter the 6-digit code from your authenticator app")}</p>
            <div class="row justify-content-center">
              <div class="col-sm-8">
                <input id="totp-input" type="text" maxlength="6" class="form-control form-control-lg text-center"
                  placeholder="000000" autocomplete="off"
                  style="font-size:1.75em;letter-spacing:0.5em;font-weight:500;font-family:monospace;border-width:2px;border-color:${state.currentTwoFAPinStatus === "invalid" ? "#dc3545" : "#ced4da"}">
              </div>
            </div>
            <div class="row justify-content-center mt-2" aria-live="polite">
              <div class="col-sm-8">${statusHtml}<span class="visually-hidden">${srStatus}</span></div>
            </div>
          </div>
          <hr>
          <div class="text-center">
            <button type="button" class="btn btn-outline-secondary" id="cancel2FABtn">
              <i class="fa-solid fa-xmark me-1"></i>${t("Cancel")}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function renderSuccess() {
  let codesHtml;
  if (state.TwoFARecoveryCodes.length) {
    codesHtml = state.TwoFARecoveryCodes.map(
      (code, i) =>
        `<div><span class="text-muted me-2">${String(i + 1).padStart(2, "0")}.</span><code>${escapeHtml(formatRecoveryCode(code))}</code></div>`,
    ).join("");
  } else {
    codesHtml = `<p class="text-muted text-center mb-0">${t("Loading recovery codes")}...</p>`;
  }

  return `<div class="row">
    <div class="col-lg-8">
      <div class="card card-outline card-success">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="fa-solid fa-circle-check me-2 text-success"></i>${t("Setup Complete")}</h4>
        </div>
        <div class="card-body">
          <p class="text-muted text-center mb-4">${t("Your authenticator app has been successfully enrolled")}</p>
          <hr>
          <div>
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-key me-2 text-warning"></i>${t("Recovery Codes")}</h6>
            <div class="callout callout-warning">
              <strong>${t("Important")}:</strong>
              <span class="small">${t("Save these recovery codes in a safe location. You can use them to access your account if you lose access to your authenticator app.")}</span>
            </div>
            <div style="background-color:#f8f9fa;padding:16px;border-radius:4px;border:1px solid #dee2e6;font-family:monospace;font-size:0.9em;line-height:2">
              ${codesHtml}
            </div>
            <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="printCodesBtn">
                  <i class="fa-solid fa-print me-1"></i>${t("Print")}
                </button>
                <button type="button" class="btn btn-outline-secondary" id="copyCodesBtn">
                  <i class="fa-solid fa-copy me-1"></i>${t("Copy")}
                </button>
                <button type="button" class="btn btn-outline-secondary" id="downloadCodesBtn">
                  <i class="fa-solid fa-download me-1"></i>${t("Download")}
                </button>
              </div>
              <a href="${CRMRoot}/v2/user/current/manage2fa" class="btn btn-primary">
                <i class="fa-solid fa-check me-1"></i>${t("Done")}
              </a>
            </div>
          </div>
          <div class="callout callout-info mt-4 mb-0 small">
            <i class="fa-solid fa-circle-info me-1"></i>${t("You can now use your authenticator app to sign in. Each code is valid for 30 seconds.")}
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function renderStatusEnabled() {
  return `<div class="row">
    <div class="col-lg-8">
      <div class="card card-outline card-success">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="fa-solid fa-shield me-2"></i>${t("Two-Factor Authentication")}</h4>
        </div>
        <div class="card-body">
          <div class="text-center mb-4">
            <span class="badge bg-success" style="font-size:1em;padding:0.4rem 1rem">
              <i class="fa-solid fa-circle-check me-1"></i>${t("Enabled")}
            </span>
          </div>
          <div class="callout callout-info">
            <h6 class="fw-bold mb-2">${t("Your account is protected")}</h6>
            <ul class="mb-0 ps-3 small">
              <li>${t("Each time you sign in, you'll need to confirm with a code from your authenticator app")}</li>
              <li>${t("You can also use backup recovery codes if you lose access to your app")}</li>
            </ul>
          </div>
          <hr>
          <div class="text-center">
            <button type="button" class="btn btn-outline-danger" id="disable2FABtn">
              <i class="fa-solid fa-xmark me-1"></i>${t("Disable Two-Factor Authentication")}
            </button>
          </div>
          <div class="callout callout-warning mt-4 mb-0 small">
            <i class="fa-solid fa-lock me-1"></i>${t("Two-factor authentication is one of the best ways to protect your account. We strongly recommend keeping it enabled.")}
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

// ---------------------------------------------------------------------------
// Render & bind
// ---------------------------------------------------------------------------

function render() {
  const container = getContainer();
  if (!container) return;

  let html;
  switch (state.currentView) {
    case "loading":
      html = renderLoading();
      break;
    case "intro":
      html = renderIntro();
      break;
    case "BeginEnroll":
      html = renderQRCode();
      break;
    case "success":
      html = renderSuccess();
      break;
    case "status-enabled":
      html = renderStatusEnabled();
      break;
    default:
      html = `<h4>${t("Two Factor Enrollment Error")}</h4>`;
  }

  container.innerHTML = html;
  bindEvents();
}

function bindEvents() {
  // Intro: Get Started button
  const beginBtn = document.getElementById("begin2faEnrollment");
  if (beginBtn) {
    beginBtn.addEventListener("click", () => {
      requestNewBarcode();
      state.currentView = "BeginEnroll";
      render();
    });
  }

  // QR screen: new code button
  const newQRBtn = document.getElementById("newQRCodeBtn");
  if (newQRBtn) {
    newQRBtn.addEventListener("click", () => requestNewBarcode());
  }

  // QR screen: cancel button
  const cancelBtn = document.getElementById("cancel2FABtn");
  if (cancelBtn) {
    cancelBtn.addEventListener("click", () => remove2FA());
  }

  // QR screen: TOTP input — set value via DOM (not interpolation) to prevent XSS
  const totpInput = document.getElementById("totp-input");
  if (totpInput) {
    totpInput.value = state.currentTwoFAPin;
    totpInput.addEventListener("input", (e) => handlePinChange(e.target.value));
    // Restore focus after re-render if the user was typing
    if (state.currentTwoFAPinStatus === "invalid" || state.currentTwoFAPinStatus === "incomplete") {
      totpInput.focus();
    }
  }

  // Success: print button
  const printBtn = document.getElementById("printCodesBtn");
  if (printBtn) {
    printBtn.addEventListener("click", () => window.print());
  }

  // Success: copy button
  const copyBtn = document.getElementById("copyCodesBtn");
  if (copyBtn) {
    copyBtn.addEventListener("click", () => {
      const text = state.TwoFARecoveryCodes.map((c, i) => `${String(i + 1).padStart(2, "0")}. ${formatRecoveryCode(c)}`).join("\n");
      navigator.clipboard.writeText(text).then(() => {
        copyBtn.innerHTML = `<i class="fa-solid fa-check me-1"></i>${t("Copied!")}`;
        setTimeout(() => {
          copyBtn.innerHTML = `<i class="fa-solid fa-copy me-1"></i>${t("Copy")}`;
        }, 2000);
      });
    });
  }

  // Success: download button
  const downloadBtn = document.getElementById("downloadCodesBtn");
  if (downloadBtn) {
    downloadBtn.addEventListener("click", () => {
      const text = state.TwoFARecoveryCodes.map((c, i) => `${String(i + 1).padStart(2, "0")}. ${formatRecoveryCode(c)}`).join("\n");
      const blob = new Blob([text], { type: "text/plain" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "churchcrm-recovery-codes.txt";
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  // Status enabled: disable button
  const disableBtn = document.getElementById("disable2FABtn");
  if (disableBtn) {
    disableBtn.addEventListener("click", () => {
      if (
        window.confirm(
          t("Are you sure you want to disable two-factor authentication? Your account will be less secure."),
        )
      ) {
        disable2FA();
      }
    });
  }
}

// ---------------------------------------------------------------------------
// API actions
// ---------------------------------------------------------------------------

function loadInitialStatus() {
  fetchJSON(`${CRMRoot}/api/user/current/2fa-status`)
    .then((data) => {
      state.is2FAEnabled = data.IsEnabled;
      state.initialLoadComplete = true;
      state.currentView = data.IsEnabled ? "status-enabled" : "intro";
      render();
    })
    .catch(() => {
      state.initialLoadComplete = true;
      state.currentView = "intro";
      render();
    });
}

function requestNewBarcode() {
  fetchJSON(`${CRMRoot}/api/user/current/refresh2fasecret`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  })
    .then((data) => {
      state.TwoFAQRCodeDataUri = data.TwoFAQRCodeDataUri;
      // Update only the QR image if we're on the right screen
      const img = document.getElementById("2faQrCodeDataUri");
      if (img) {
        img.src = data.TwoFAQRCodeDataUri;
      }
    })
    .catch(() => notifyError(t("Failed to generate QR code. Please try again.")));
}

function requestRecoveryCodes() {
  fetchJSON(`${CRMRoot}/api/user/current/refresh2farecoverycodes`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  })
    .then((data) => {
      state.TwoFARecoveryCodes = data.TwoFARecoveryCodes;
      render();
    })
    .catch(() => notifyError(t("Failed to load recovery codes. Please refresh the page.")));
}

function remove2FA() {
  fetchJSON(`${CRMRoot}/api/user/current/remove2fasecret`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  })
    .then(() => {
      state.TwoFAQRCodeDataUri = "";
      state.currentView = "intro";
      render();
    })
    .catch(() => notifyError(t("Failed to cancel enrollment. Please try again.")));
}

function disable2FA() {
  fetchJSON(`${CRMRoot}/api/user/current/remove2fasecret`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  })
    .then(() => {
      state.is2FAEnabled = false;
      state.currentView = "intro";
      render();
      if (window.CRM?.notify) {
        window.CRM.notify(t("Two-factor authentication has been disabled"), { type: "success" });
      }
    })
    .catch(() => notifyError(t("Failed to disable two-factor authentication. Please try again.")));
}

let pendingPinVerification = "";

function handlePinChange(value) {
  state.currentTwoFAPin = value;
  if (value.length === 6) {
    state.currentTwoFAPinStatus = "pending";
    pendingPinVerification = value;
    render();
    fetchJSON(`${CRMRoot}/api/user/current/test2FAEnrollmentCode`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ enrollmentCode: value }),
    })
      .then((data) => {
        // Ignore stale responses if the user changed the code while request was pending
        if (pendingPinVerification !== value) return;
        if (data.IsEnrollmentCodeValid) {
          requestRecoveryCodes();
          state.is2FAEnabled = true;
          state.currentView = "success";
          render();
        } else {
          state.currentTwoFAPinStatus = "invalid";
          render();
        }
      })
      .catch(() => {
        if (pendingPinVerification !== value) return;
        state.currentTwoFAPinStatus = "invalid";
        render();
        notifyError(t("Verification failed. Please try again."));
      });
  } else {
    state.currentTwoFAPinStatus = "incomplete";
    render();
  }
}

// ---------------------------------------------------------------------------
// Init on DOM ready
// ---------------------------------------------------------------------------

$(document).ready(() => {
  if (getContainer()) {
    render();
    loadInitialStatus();
  }
});
