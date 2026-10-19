/**
 * Two-Factor Authentication Enrollment
 *
 * Multi-step wizard: loading → intro → scan QR → success / status-enabled.
 * Uses Bootstrap 5 cards and vanilla fetch against the 2FA API.
 */

const CRMRoot = window.CRM.root;
const t = (key) => (window.i18next ? window.i18next.t(key) : key);

function fetchJSON(url, opts = {}) {
  const requestOptions = { credentials: "include", ...opts };
  const method = (requestOptions.method || "GET").toUpperCase();
  const headers = new Headers(requestOptions.headers || {});
  if (["POST", "PUT", "PATCH", "DELETE"].includes(method)) {
    const csrfToken = getContainer()?.dataset.csrfToken || document.querySelector('input[name="csrf_token"]')?.value;
    if (csrfToken) headers.set("X-CSRF-Token", csrfToken);
  }
  requestOptions.headers = headers;

  return fetch(url, requestOptions).then(async (response) => {
    let data = {};
    if (response.status !== 204) {
      const text = await response.text();
      if (text) {
        try {
          data = JSON.parse(text);
        } catch (_error) {
          data = {};
        }
      }
    }
    if (!response.ok) {
      const error = new Error(data.error || data.message || `HTTP ${response.status}`);
      error.status = response.status;
      throw error;
    }

    return data;
  });
}

function escapeHtml(str) {
  if (!str) return "";
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
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
  requiresReauthentication: true,
  reauthenticationError: "",
  reauthenticationReturnView: "intro",
  pendingSecurityAction: null,
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

function renderReauthentication() {
  const errorHtml = state.reauthenticationError
    ? `<div class="alert alert-danger" role="alert">${escapeHtml(state.reauthenticationError)}</div>`
    : "";

  return `<div class="row">
    <div class="col-lg-8">
      <div class="card card-outline card-primary">
        <div class="card-header">
          <h4 class="mb-0"><i class="fa-solid fa-lock me-2"></i>${t("Confirm Your Password")}</h4>
        </div>
        <div class="card-body">
          <p class="text-muted">${t("For your security, enter your current password before changing two-factor authentication settings.")}</p>
          ${errorHtml}
          <form id="securityReauthenticationForm">
            <div class="mb-3">
              <label for="current-password" class="form-label">${t("Current Password")}</label>
              <input id="current-password" name="currentPassword" type="password" class="form-control"
                autocomplete="current-password" required maxlength="1024">
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">${t("Continue")}</button>
              <button type="button" id="cancelSecurityReauthentication" class="btn btn-outline-secondary">${t("Cancel")}</button>
            </div>
          </form>
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
              <span class="badge bg-primary me-3 two-factor-step-badge">1</span>
              <div><strong>${t("Sign In")}</strong><div class="text-muted small">${t("Enter your username and password as usual")}</div></div>
            </div>
            <div class="d-flex align-items-start mb-3">
              <span class="badge bg-primary me-3 two-factor-step-badge">2</span>
              <div><strong>${t("One-Time Code")}</strong><div class="text-muted small">${t("Confirm with a code from your authenticator app")}</div></div>
            </div>
            <div class="d-flex align-items-start">
              <span class="badge bg-primary me-3 two-factor-step-badge">3</span>
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
              <span class="badge bg-primary me-2 two-factor-step-badge-sm">1</span>${t("Scan QR Code")}
            </h6>
            <p class="text-muted small mb-3">${t("Open your authenticator app and scan this QR code")}</p>
            <div class="text-center mb-3">
              <div class="d-inline-block p-3 two-factor-qr-frame">
                <img id="2faQrCodeDataUri" src="${state.TwoFAQRCodeDataUri}" alt="2FA QR Code" class="two-factor-qr-image">
              </div>
            </div>
            <p class="text-muted small text-center mb-0">
              ${t("Can't scan?")} <button type="button" class="btn btn-link btn-sm p-0" id="newQRCodeBtn">${t("Generate new code")}</button>
            </p>
          </div>
          <hr>
          <div class="mb-3">
            <h6 class="fw-bold d-flex align-items-center mb-3">
              <span class="badge bg-primary me-2 two-factor-step-badge-sm">2</span>${t("Verify Code")}
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
        `<div><span class="text-muted me-2">${String(i + 1).padStart(2, "0")}.</span><code>${escapeHtml(code)}</code></div>`,
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
            <div class="bg-surface-secondary border two-factor-backup-codes">
              ${codesHtml}
            </div>
            <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="printCodesBtn">
                  <i class="fa-solid fa-print me-1"></i>${t("Print")}
                </button>
                <button type="button" class="btn btn-outline-secondary" id="copyCodesBtn" ${state.TwoFARecoveryCodes.length === 0 ? "disabled" : ""}>
                  <i class="fa-solid fa-copy me-1"></i>${t("Copy")}
                </button>
                <button type="button" class="btn btn-outline-secondary" id="downloadCodesBtn" ${state.TwoFARecoveryCodes.length === 0 ? "disabled" : ""}>
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
            <span class="badge bg-success two-factor-enabled-badge">
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
    case "reauthenticate":
      html = renderReauthentication();
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
  const reauthenticationForm = document.getElementById("securityReauthenticationForm");
  if (reauthenticationForm) {
    reauthenticationForm.addEventListener("submit", (event) => {
      event.preventDefault();
      submitSecurityReauthentication();
    });
    document.getElementById("current-password")?.focus();
  }

  const cancelReauthenticationBtn = document.getElementById("cancelSecurityReauthentication");
  if (cancelReauthenticationBtn) {
    cancelReauthenticationBtn.addEventListener("click", cancelSecurityReauthentication);
  }

  // Intro: Get Started button
  const beginBtn = document.getElementById("begin2faEnrollment");
  if (beginBtn) {
    beginBtn.addEventListener("click", () => startEnrollment());
  }

  // QR screen: new code button
  const newQRBtn = document.getElementById("newQRCodeBtn");
  if (newQRBtn) {
    newQRBtn.addEventListener("click", () => requestNewBarcode("new-barcode", "BeginEnroll"));
  }

  // QR screen: cancel button
  const cancelBtn = document.getElementById("cancel2FABtn");
  if (cancelBtn) {
    cancelBtn.addEventListener("click", cancelEnrollment);
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
      const text = state.TwoFARecoveryCodes.map((c, i) => `${String(i + 1).padStart(2, "0")}. ${c}`).join("\n");
      const showCopied = () => {
        copyBtn.innerHTML = `<i class="fa-solid fa-check me-1"></i>${t("Copied!")}`;
        setTimeout(() => {
          copyBtn.innerHTML = `<i class="fa-solid fa-copy me-1"></i>${t("Copy")}`;
        }, 2000);
      };
      if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
        navigator.clipboard
          .writeText(text)
          .then(showCopied)
          .catch(() => {
            // Fallback for permission denied
            const ta = document.createElement("textarea");
            ta.value = text;
            ta.setAttribute("readonly", "");
            ta.style.position = "absolute";
            ta.style.left = "-9999px";
            document.body.appendChild(ta);
            try {
              ta.select();
              document.execCommand("copy");
              document.body.removeChild(ta);
              showCopied();
            } catch (_e) {
              document.body.removeChild(ta);
              if (typeof bootbox !== "undefined") {
                bootbox.alert(t("Unable to copy to clipboard. Please copy the codes manually."));
              }
            }
          });
      } else {
        // Non-secure context — clipboard API unavailable
        if (typeof bootbox !== "undefined") {
          bootbox.alert(t("Clipboard not available. Please use the Download button to save your codes."));
        }
      }
    });
  }

  // Success: download button
  const downloadBtn = document.getElementById("downloadCodesBtn");
  if (downloadBtn) {
    downloadBtn.addEventListener("click", () => {
      const text = state.TwoFARecoveryCodes.map((c, i) => `${String(i + 1).padStart(2, "0")}. ${c}`).join("\n");
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
        runSecurityAction("disable", "status-enabled", disable2FA);
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
      state.requiresReauthentication = Boolean(data.RequiresReauthentication);
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

function updateCsrfToken(token) {
  const container = getContainer();
  if (container && typeof token === "string" && token) {
    container.dataset.csrfToken = token;
  }
}

function beginSecurityReauthentication(action, returnView) {
  state.pendingSecurityAction = action;
  state.reauthenticationReturnView = returnView;
  state.reauthenticationError = "";
  state.currentView = "reauthenticate";
  render();
}

function cancelSecurityReauthentication() {
  state.pendingSecurityAction = null;
  state.reauthenticationError = "";
  state.currentView = state.reauthenticationReturnView;
  render();
}

function runSecurityAction(action, returnView, callback) {
  if (state.requiresReauthentication) {
    beginSecurityReauthentication(action, returnView);
    return;
  }
  callback();
}

function handleSecurityActionFailure(error, action, returnView, fallbackMessage) {
  if (error.status === 428) {
    state.requiresReauthentication = true;
    beginSecurityReauthentication(action, returnView);
    return;
  }
  if (error.status === 401) {
    window.location.assign(`${CRMRoot}/session/begin`);
    return;
  }
  if (error.status === 403) {
    notifyError(t("This page's security token expired. Reload the page and try again."));
    return;
  }
  notifyError(fallbackMessage);
}

function executePendingSecurityAction(action) {
  switch (action) {
    case "begin":
      requestNewBarcode("begin", "intro");
      break;
    case "new-barcode":
      requestNewBarcode("new-barcode", "BeginEnroll");
      break;
    case "disable":
      disable2FA();
      break;
    case "confirm-pin":
      // Expiring the old grant deliberately discarded the server-side draft.
      // Provision a replacement instead of retrying against the stale QR code.
      notifyError(t("The enrollment code expired. Scan the new QR code and try again."));
      requestNewBarcode("new-barcode", "BeginEnroll");
      break;
    case "recovery-codes":
      requestRecoveryCodes();
      break;
    default:
      state.currentView = state.reauthenticationReturnView;
      render();
  }
}

function submitSecurityReauthentication() {
  const passwordInput = document.getElementById("current-password");
  const currentPassword = passwordInput?.value || "";
  if (!currentPassword) {
    state.reauthenticationError = t("Current password is required.");
    render();
    return;
  }

  const pendingAction = state.pendingSecurityAction;
  fetchJSON(`${CRMRoot}/api/user/current/reauthenticate`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ currentPassword }),
  })
    .then((data) => {
      updateCsrfToken(data.CSRFToken);
      state.requiresReauthentication = false;
      state.reauthenticationError = "";
      state.pendingSecurityAction = null;
      executePendingSecurityAction(pendingAction);
    })
    .catch((error) => {
      if (error.status === 401) {
        window.location.assign(`${CRMRoot}/session/begin`);
        return;
      }
      if (error.status === 403) {
        state.reauthenticationError = t("This page's security token expired. Reload the page and try again.");
        render();
        return;
      }
      state.reauthenticationError =
        error.status === 422
          ? t("The current password was not accepted.")
          : t("Unable to confirm your password. Please try again.");
      render();
    });
}

function startEnrollment() {
  runSecurityAction("begin", "intro", () => requestNewBarcode("begin", "intro"));
}

function requestNewBarcode(action, returnView) {
  fetchJSON(`${CRMRoot}/api/user/current/refresh2fasecret`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  })
    .then((data) => {
      state.TwoFAQRCodeDataUri = data.TwoFAQRCodeDataUri;
      state.currentTwoFAPin = "";
      state.currentTwoFAPinStatus = "";
      state.currentView = "BeginEnroll";
      render();
    })
    .catch((error) =>
      handleSecurityActionFailure(error, action, returnView, t("Failed to generate QR code. Please try again.")),
    );
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
    .catch((error) =>
      handleSecurityActionFailure(
        error,
        "recovery-codes",
        "success",
        t("Failed to load recovery codes. Please refresh the page."),
      ),
    );
}

function cancelEnrollment() {
  fetchJSON(`${CRMRoot}/api/user/current/cancel2faenrollment`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  })
    .catch(() => notifyError(t("The enrollment was closed, but its server-side draft could not be cleared.")))
    .finally(() => {
      pendingPinVerification = "";
      state.TwoFAQRCodeDataUri = "";
      state.currentTwoFAPin = "";
      state.currentTwoFAPinStatus = "";
      state.currentView = state.is2FAEnabled ? "status-enabled" : "intro";
      render();
    });
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
    .catch((error) =>
      handleSecurityActionFailure(
        error,
        "disable",
        "status-enabled",
        t("Failed to disable two-factor authentication. Please try again."),
      ),
    );
}

let pendingPinVerification = "";

function handlePinChange(value) {
  state.currentTwoFAPin = value;
  if (value.length === 6) {
    state.currentTwoFAPinStatus = "pending";
    pendingPinVerification = value;
    render();
    verifyEnrollmentCode(value);
  } else {
    state.currentTwoFAPinStatus = "incomplete";
    render();
  }
}

function verifyEnrollmentCode(value) {
  fetchJSON(`${CRMRoot}/api/user/current/test2FAEnrollmentCode`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ enrollmentCode: value }),
  })
    .then((data) => {
      // Ignore stale responses if the user changed the code while request was pending.
      if (pendingPinVerification !== value) return;
      if (data.IsEnrollmentCodeValid) {
        state.is2FAEnabled = true;
        state.currentView = "success";
        render();
        requestRecoveryCodes();
      } else {
        state.currentTwoFAPinStatus = "invalid";
        render();
      }
    })
    .catch((error) => {
      if (pendingPinVerification !== value) return;
      if (error.status === 428) {
        state.requiresReauthentication = true;
        beginSecurityReauthentication("confirm-pin", "BeginEnroll");
        return;
      }
      if (error.status === 401) {
        window.location.assign(`${CRMRoot}/session/begin`);
        return;
      }
      if (error.status === 403) {
        notifyError(t("This page's security token expired. Reload the page and try again."));
        return;
      }
      state.currentTwoFAPinStatus = "invalid";
      render();
      notifyError(t("Verification failed. Please try again."));
    });
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
