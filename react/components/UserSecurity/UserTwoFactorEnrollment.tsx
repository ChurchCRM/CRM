import * as React from "react";
import CRMRoot from "../../window-context-service.jsx";

const TwoFAEnrollmentWelcome: React.FunctionComponent<{
  nextButtonEventHandler: () => void;
}> = ({ nextButtonEventHandler }) => {
  return (
    <div className="col-lg-8">
      <div className="card card-outline card-primary">
        <div className="card-header text-center">
          <h4 className="mb-0">
            <i className="fa fa-shield mr-2"></i>
            {window.i18next.t("Enable Two-Factor Authentication")}
          </h4>
        </div>
        <div className="card-body">
          <p className="text-muted text-center mb-4">
            {window.i18next.t("Add an extra layer of security to your account")}
          </p>

          {/* How it works - compact list */}
          <div className="mb-4">
            <div className="d-flex align-items-start mb-3">
              <span className="badge badge-primary mr-3" style={{ minWidth: "28px", padding: "6px 0" }}>
                1
              </span>
              <div>
                <strong>{window.i18next.t("Sign In")}</strong>
                <div className="text-muted small">{window.i18next.t("Enter your username and password as usual")}</div>
              </div>
            </div>
            <div className="d-flex align-items-start mb-3">
              <span className="badge badge-primary mr-3" style={{ minWidth: "28px", padding: "6px 0" }}>
                2
              </span>
              <div>
                <strong>{window.i18next.t("One-Time Code")}</strong>
                <div className="text-muted small">
                  {window.i18next.t("Confirm with a code from your authenticator app")}
                </div>
              </div>
            </div>
            <div className="d-flex align-items-start">
              <span className="badge badge-primary mr-3" style={{ minWidth: "28px", padding: "6px 0" }}>
                3
              </span>
              <div>
                <strong>{window.i18next.t("Secure")}</strong>
                <div className="text-muted small">{window.i18next.t("Your account is now protected")}</div>
              </div>
            </div>
          </div>

          <hr />

          {/* Requirements */}
          <div className="callout callout-warning">
            <h6 className="font-weight-bold">{window.i18next.t("Before You Start")}</h6>
            <ul className="mb-0 pl-3 small">
              <li>
                {window.i18next.t(
                  "Have your authenticator app ready (Google Authenticator, Microsoft Authenticator, Authy, etc.)",
                )}
              </li>
              <li>{window.i18next.t("This will replace any previously enrolled 2FA methods")}</li>
              <li>{window.i18next.t("You'll receive backup codes that can be used if you lose access to your app")}</li>
            </ul>
          </div>

          {/* CTA Button */}
          <div className="text-center mt-4">
            <button
              id="begin2faEnrollment"
              className="btn btn-primary btn-lg btn-block"
              onClick={() => {
                nextButtonEventHandler();
              }}
            >
              <i className="fa fa-arrow-right mr-2"></i>
              {window.i18next.t("Get Started")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

const TwoFAEnrollmentGetQR: React.FunctionComponent<{
  TwoFAQRCodeDataUri: string;
  newQRCode: () => void;
  remove2FA: () => void;
  validationCodeChangeHandler: (event: React.ChangeEvent<HTMLInputElement>) => void;
  currentTwoFAPin?: string;
  currentTwoFAPinStatus: string;
}> = ({
  TwoFAQRCodeDataUri,
  newQRCode,
  remove2FA,
  validationCodeChangeHandler,
  currentTwoFAPin,
  currentTwoFAPinStatus,
}) => {
  return (
    <div className="col-lg-8">
      <div className="card card-outline card-primary">
        <div className="card-header text-center">
          <h4 className="mb-0">
            <i className="fa fa-qrcode mr-2"></i>
            {window.i18next.t("Set Up Authenticator")}
          </h4>
        </div>
        <div className="card-body">
          {/* Step 1: Scan QR Code */}
          <div className="mb-4">
            <h6 className="font-weight-bold d-flex align-items-center mb-3">
              <span className="badge badge-primary mr-2" style={{ minWidth: "24px", padding: "4px 0" }}>
                1
              </span>
              {window.i18next.t("Scan QR Code")}
            </h6>
            <p className="text-muted small mb-3">
              {window.i18next.t("Open your authenticator app and scan this QR code")}
            </p>

            <div className="text-center mb-3">
              <div
                className="d-inline-block p-3"
                style={{
                  border: "2px solid #dee2e6",
                  borderRadius: "8px",
                  backgroundColor: "#fff",
                }}
              >
                <img
                  id="2faQrCodeDataUri"
                  src={TwoFAQRCodeDataUri}
                  alt="2FA QR Code"
                  style={{ maxWidth: "200px", height: "auto", display: "block" }}
                />
              </div>
            </div>

            <p className="text-muted small text-center mb-0">
              {window.i18next.t("Cant scan")}{" "}
              <button className="btn btn-link btn-sm p-0" onClick={() => newQRCode()}>
                {window.i18next.t("Generate new code")}
              </button>
            </p>
          </div>

          <hr />

          {/* Step 2: Verify Code */}
          <div className="mb-3">
            <h6 className="font-weight-bold d-flex align-items-center mb-3">
              <span className="badge badge-primary mr-2" style={{ minWidth: "24px", padding: "4px 0" }}>
                2
              </span>
              {window.i18next.t("Verify Code")}
            </h6>
            <p className="text-muted small mb-3">
              {window.i18next.t("Enter the 6-digit code from your authenticator app")}
            </p>

            <div className="row justify-content-center">
              <div className="col-sm-8">
                <input
                  id="totp-input"
                  type="text"
                  maxLength={6}
                  className="form-control form-control-lg text-center"
                  onChange={validationCodeChangeHandler}
                  value={currentTwoFAPin}
                  placeholder="000000"
                  autoComplete="off"
                  style={{
                    fontSize: "1.75em",
                    letterSpacing: "0.5em",
                    fontWeight: "500",
                    fontFamily: "monospace",
                    borderWidth: "2px",
                    borderColor: currentTwoFAPinStatus === "invalid" ? "#dc3545" : "#ced4da",
                  }}
                />
              </div>
            </div>

            {/* Status Messages */}
            <div className="row justify-content-center mt-2" aria-live="polite">
              <div className="col-sm-8">
                {currentTwoFAPinStatus === "pending" && (
                  <div className="text-center text-info small">
                    <span className="fa fa-spinner fa-spin mr-1" aria-hidden="true"></span>
                    {window.i18next.t("Verifying")}&hellip;
                  </div>
                )}
                {currentTwoFAPinStatus === "invalid" && (
                  <div className="text-center text-danger small">
                    <i className="fa fa-times-circle mr-1"></i>
                    {window.i18next.t("Code is invalid")} &ndash; {window.i18next.t("Please try again")}
                  </div>
                )}
                <span className="sr-only">
                  {currentTwoFAPinStatus === "pending" && window.i18next.t("Validation pending")}
                  {currentTwoFAPinStatus === "invalid" && window.i18next.t("Code is invalid")}
                  {currentTwoFAPinStatus === "incomplete" && window.i18next.t("Code incomplete")}
                </span>
              </div>
            </div>
          </div>

          <hr />

          {/* Action Buttons */}
          <div className="text-center">
            <button className="btn btn-outline-secondary" onClick={() => remove2FA()}>
              <i className="fa fa-times mr-1"></i>
              {window.i18next.t("Cancel")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

const TwoFAEnrollmentSuccess: React.FunctionComponent<{
  TwoFARecoveryCodes?: string[];
}> = ({ TwoFARecoveryCodes = [] }) => {
  return (
    <div className="col-lg-8">
      <div className="card card-outline card-success">
        <div className="card-header text-center">
          <h4 className="mb-0">
            <i className="fa fa-check-circle mr-2 text-success"></i>
            {window.i18next.t("Setup Complete")}
          </h4>
        </div>
        <div className="card-body">
          <p className="text-muted text-center mb-4">
            {window.i18next.t("Your authenticator app has been successfully enrolled")}
          </p>

          <hr />

          {/* Recovery Codes Section */}
          <div>
            <h6 className="font-weight-bold mb-3">
              <i className="fa fa-key mr-2 text-warning"></i>
              {window.i18next.t("Recovery Codes")}
            </h6>

            <div className="callout callout-warning">
              <strong>{window.i18next.t("Important")}:</strong>{" "}
              <span className="small">
                {window.i18next.t(
                  "Save these recovery codes in a safe location. You can use them to access your account if you lose access to your authenticator app.",
                )}
              </span>
            </div>

            <div
              style={{
                backgroundColor: "#f8f9fa",
                padding: "16px",
                borderRadius: "4px",
                border: "1px solid #dee2e6",
                fontFamily: "monospace",
                fontSize: "0.9em",
                lineHeight: "2",
              }}
            >
              {TwoFARecoveryCodes.length ? (
                <div>
                  {TwoFARecoveryCodes.map((code: string, index: number) => (
                    <div key={index}>
                      <span className="text-muted mr-2">{String(index + 1).padStart(2, "0")}.</span>
                      <code>{code}</code>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-muted text-center mb-0">{window.i18next.t("Loading recovery codes")}...</p>
              )}
            </div>

            {/* Action Buttons */}
            <div className="mt-4 d-flex justify-content-between">
              <button
                className="btn btn-outline-secondary"
                onClick={() => {
                  window.print();
                }}
              >
                <i className="fa fa-print mr-1"></i>
                {window.i18next.t("Print")}
              </button>
              <a href={`${CRMRoot}/v2/user/current/manage2fa`} className="btn btn-primary">
                <i className="fa fa-check mr-1"></i>
                {window.i18next.t("Done")}
              </a>
            </div>
          </div>

          {/* Info Box */}
          <div className="callout callout-info mt-4 mb-0 small">
            <i className="fa fa-info-circle mr-1"></i>
            {window.i18next.t("You can now use your authenticator app to sign in. Each code is valid for 30 seconds.")}
          </div>
        </div>
      </div>
    </div>
  );
};

const TwoFAStatusEnabled: React.FunctionComponent<{
  onDisable: () => void;
}> = ({ onDisable }) => {
  return (
    <div className="col-lg-8">
      <div className="card card-outline card-success">
        <div className="card-header text-center">
          <h4 className="mb-0">
            <i className="fa fa-shield mr-2"></i>
            {window.i18next.t("Two-Factor Authentication")}
          </h4>
        </div>
        <div className="card-body">
          {/* Status Badge */}
          <div className="text-center mb-4">
            <span className="badge badge-success" style={{ fontSize: "1em", padding: "0.4rem 1rem" }}>
              <i className="fa fa-check-circle mr-1"></i>
              {window.i18next.t("Enabled")}
            </span>
          </div>

          {/* Info Section */}
          <div className="callout callout-info">
            <h6 className="font-weight-bold mb-2">{window.i18next.t("Your account is protected")}</h6>
            <ul className="mb-0 pl-3 small">
              <li>
                {window.i18next.t(
                  "Each time you sign in, youll need to confirm with a code from your authenticator app",
                )}
              </li>
              <li>{window.i18next.t("You can also use backup recovery codes if you lose access to your app")}</li>
            </ul>
          </div>

          <hr />

          {/* Actions */}
          <div className="text-center">
            <button
              className="btn btn-outline-danger"
              onClick={() => {
                if (
                  window.confirm(
                    window.i18next.t(
                      "Are you sure you want to disable two-factor authentication? Your account will be less secure.",
                    ),
                  )
                ) {
                  onDisable();
                }
              }}
            >
              <i className="fa fa-times mr-1"></i>
              {window.i18next.t("Disable Two-Factor Authentication")}
            </button>
          </div>

          {/* Info Box */}
          <div className="callout callout-warning mt-4 mb-0 small">
            <i className="fa fa-lock mr-1"></i>
            {window.i18next.t(
              "Two-factor authentication is one of the best ways to protect your account. We strongly recommend keeping it enabled.",
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

interface TwoFactorEnrollmentState {
  currentView: string;
  is2FAEnabled?: boolean;
  initialLoadComplete?: boolean;
  TwoFAQRCodeDataUri?: string;
  currentTwoFAPin?: string;
  currentTwoFAPinStatus?: string;
  TwoFARecoveryCodes: string[];
}

class UserTwoFactorEnrollment extends React.Component<Record<string, unknown>, TwoFactorEnrollmentState> {
  constructor(props: Record<string, unknown>) {
    super(props);

    this.state = {
      currentView: "loading",
      is2FAEnabled: false,
      initialLoadComplete: false,
      TwoFARecoveryCodes: [],
    };

    this.nextButtonEventHandler = this.nextButtonEventHandler.bind(this);
    this.requestNew2FABarcode = this.requestNew2FABarcode.bind(this);
    this.requestNew2FARecoveryCodes = this.requestNew2FARecoveryCodes.bind(this);
    this.remove2FAForuser = this.remove2FAForuser.bind(this);
    this.validationCodeChangeHandler = this.validationCodeChangeHandler.bind(this);
    this.disable2FA = this.disable2FA.bind(this);
  }

  componentDidMount() {
    // Load 2FA status on component mount
    this.loadInitial2FAStatus();
  }

  loadInitial2FAStatus() {
    fetch(`${CRMRoot}/api/user/current/2fa-status`, {
      credentials: "include",
      method: "GET",
      headers: {
        Accept: "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        this.setState({
          is2FAEnabled: data.IsEnabled,
          initialLoadComplete: true,
          currentView: data.IsEnabled ? "status-enabled" : "intro",
        });
      })
      .catch(() => {
        this.setState({
          initialLoadComplete: true,
          currentView: "intro",
        });
      });
  }

  nextButtonEventHandler() {
    this.requestNew2FABarcode();
    this.setState({
      currentView: "BeginEnroll",
    });
  }

  requestNew2FABarcode() {
    fetch(`${CRMRoot}/api/user/current/refresh2fasecret`, {
      credentials: "include",
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        this.setState({ TwoFAQRCodeDataUri: data.TwoFAQRCodeDataUri });
      });
  }

  requestNew2FARecoveryCodes() {
    fetch(`${CRMRoot}/api/user/current/refresh2farecoverycodes`, {
      credentials: "include",
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        this.setState({ TwoFARecoveryCodes: data.TwoFARecoveryCodes });
      });
  }

  remove2FAForuser() {
    fetch(`${CRMRoot}/api/user/current/remove2fasecret`, {
      credentials: "include",
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then(() => {
        this.setState({
          TwoFAQRCodeDataUri: "",
          currentView: "intro",
        });
      });
  }

  disable2FA() {
    fetch(`${CRMRoot}/api/user/current/remove2fasecret`, {
      credentials: "include",
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then(() => {
        this.setState({
          is2FAEnabled: false,
          currentView: "intro",
        });
        (window.CRM as any).notify(window.i18next.t("Two-factor authentication has been disabled"), {
          type: "success",
        });
      });
  }

  validationCodeChangeHandler(event: React.ChangeEvent<HTMLInputElement>) {
    this.setState({
      currentTwoFAPin: event.currentTarget.value,
    });
    if (event.currentTarget.value.length === 6) {
      console.log("Checking for valid pin");
      fetch(`${CRMRoot}/api/user/current/test2FAEnrollmentCode`, {
        credentials: "include",
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ enrollmentCode: event.currentTarget.value }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.IsEnrollmentCodeValid) {
            this.requestNew2FARecoveryCodes();
            this.setState({
              is2FAEnabled: true,
              currentView: "success",
            });
          } else {
            this.setState({
              currentTwoFAPinStatus: "invalid",
            });
          }
        });
      this.setState({
        currentTwoFAPinStatus: "pending",
      });
    } else {
      this.setState({
        currentTwoFAPinStatus: "incomplete",
      });
    }
  }

  render() {
    if (!this.state.initialLoadComplete) {
      return (
        <div className="row">
          <div className="col-lg-8">
            <div className="card card-outline card-primary">
              <div className="card-body p-5 text-center">
                <div className="spinner-border" role="status">
                  <span className="sr-only">{window.i18next.t("Loading")}...</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      );
    }

    if (this.state.currentView === "status-enabled") {
      return (
        <div className="row">
          <TwoFAStatusEnabled onDisable={this.disable2FA} />
        </div>
      );
    } else if (this.state.currentView === "intro") {
      return (
        <div className="row">
          <TwoFAEnrollmentWelcome nextButtonEventHandler={this.nextButtonEventHandler} />
        </div>
      );
    } else if (this.state.currentView === "BeginEnroll") {
      return (
        <div className="row">
          <TwoFAEnrollmentGetQR
            TwoFAQRCodeDataUri={this.state.TwoFAQRCodeDataUri || ""}
            newQRCode={this.requestNew2FABarcode}
            remove2FA={this.remove2FAForuser}
            validationCodeChangeHandler={this.validationCodeChangeHandler}
            currentTwoFAPin={this.state.currentTwoFAPin}
            currentTwoFAPinStatus={this.state.currentTwoFAPinStatus || ""}
          />
        </div>
      );
    } else if (this.state.currentView === "success") {
      return (
        <div className="row">
          <TwoFAEnrollmentSuccess TwoFARecoveryCodes={this.state.TwoFARecoveryCodes} />
        </div>
      );
    } else {
      return <h4>Uh-oh</h4>;
    }
  }
}

export default UserTwoFactorEnrollment;
