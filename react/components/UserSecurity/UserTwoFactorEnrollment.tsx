import * as React from 'react';
import CRMRoot from '../../window-context-service.jsx';

const TwoFAEnrollmentWelcome: React.FunctionComponent<{nextButtonEventHandler: Function}> = ({ nextButtonEventHandler}) => {
    return (
        <div>
             <div className="col-lg-12">
                    <div className="box" id="TwoFAEnrollmentSteps">
                        <div className="box-body">
                            <div className="callout callout-warning">
                                <p>{window.i18next.t("When you click next, you'll be prompted to scan a QR code to enroll your authenticator app.")}<br/>{window.i18next.t("This will invalidate any previously configured 2 factor apps / devices")}</p>
                            </div>
                            <p>{window.i18next.t("Enrolling your ChurchCRM user account in Two Factor Authention provides an additional layer of defense against bad actors trying to access your account.")}</p>
                            <hr/>
                            <div className="col-lg-4">
                                <i className="fa fa-id-card-o"></i>
                                <p>{window.i18next.t("When you sign in to ChurchCRM, you'll still enter your username and password like normal")}</p>
                            </div>
                            <div className="col-lg-4">
                                <i className="fa fa-key"></i>
                                <p>{window.i18next.t("However, you'll also need to supply a one-time code from your authenticator device to complete your login")}</p>
                            </div>
                            <div className="col-lg-4">
                                <i className="fa fa-check-square-o"></i>
                                <p>{window.i18next.t("After successfully entering both your credentials, and the one-time code, you'll be logged in as normal")}</p>
                            </div>
                            <div className="clearfix"></div>
                            <div className="callout callout-info">
                                <p>{window.i18next.t("ChurchCRM Two factor supports any TOTP authenticator app, so you're free to choose between Microsoft Authenticator, Google Authenticator, Authy, LastPass, and others")}</p>
                            </div>
                            <button className="btn btn-success" onClick={() => {nextButtonEventHandler()}}>{window.i18next.t("Begin Two Factor Authentication Enrollment")}</button>
                    </div>
                </div>
            </div>
        </div>

    )
}

const TwoFAEnrollmentGetQR: React.FunctionComponent<{TwoFAQRCodeDataUri: string, newQRCode:Function}> = ({TwoFAQRCodeDataUri, newQRCode}) => {
    return (
        <div>
             <div className="col-lg-12">
                    <div className="box">
                        <div className="box-header">
                            <h4>{window.i18next.t("2 Factor Authentication Secret")}</h4>
                        </div>
                        <div className="box-body">
                          <img src={TwoFAQRCodeDataUri} />
                        <br />
                        <button className="btn btn-warning" onClick={() => {newQRCode()}}>{window.i18next.t("Regenerate 2 Factor Authentication Secret")}</button>
                       
                        <a id="remove2faKey" className="btn btn-warning"><i className="fa fa-repeat"></i>{window.i18next.t("Remove 2 Factor Authentication Secret")}</a>
                        </div>
                    </div>
                </div>
        </div>
    )
}


class UserTwoFactorEnrollment extends React.Component<TwoFactorEnrollmentProps, TwoFactorEnrollmentState> {
    constructor(props: TwoFactorEnrollmentProps) {
      super(props);

      this.state = {
        currentView: "intro",
        TwoFAQRCodeDataUri: ""
      }

      this.nextButtonEventHandler = this.nextButtonEventHandler.bind(this);
      this.requestNew2FABarcode = this.requestNew2FABarcode.bind(this);
    }

    nextButtonEventHandler() {
      this.setState({
        currentView:"BeginEnroll"
      });
    }

    requestNew2FABarcode() {
      fetch(CRMRoot + '/api/user/current/refresh2fasecret', {
        credentials: "include",
        method: "POST",
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      })
        .then(response => response.json())
        .then(data => {
          this.setState({ TwoFAQRCodeDataUri: data.TwoFAQRCodeDataUri })
        });
    }

    render() {  
        if (this.state.currentView === "intro") {
            return (
                <div>
                    <div className="row">
                        <TwoFAEnrollmentWelcome nextButtonEventHandler = { this.nextButtonEventHandler}  />                    
                    </div>
                </div >
            );
        }
        else {
            return (
                <div>
                    
                    <div className="row">
                        <TwoFAEnrollmentGetQR TwoFAQRCodeDataUri={this.state.TwoFAQRCodeDataUri} newQRCode={this.requestNew2FABarcode} />               
                    </div>
                </div >
            );
        }
    }
}

interface TwoFactorEnrollmentProps {

}

interface TwoFactorEnrollmentState {
  currentView: string,
  TwoFAQRCodeDataUri?: string
}
export default UserTwoFactorEnrollment;