import * as React from "react";
import * as ReactDOM from "react-dom";
import UserTwoFactorEnrollment from "./components/UserSecurity/UserTwoFactorEnrollment";

declare global {
  interface Window {
    // React does have it's own i18next implementation, but for now, lets use the one that's already being loaded
    i18next: {
      t(string): string;
    };
  }
}
$(document).ready(function () {
  ReactDOM.render(
    <UserTwoFactorEnrollment />,
    document.getElementById("two-factor-enrollment-react-app"),
  );
});
