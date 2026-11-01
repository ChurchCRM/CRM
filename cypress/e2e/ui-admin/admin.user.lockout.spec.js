/// <reference types="cypress" />

/**
 * The temporary account lockout on the admin pages (#10197).
 * login_lockout_user (907, "Login Lockout") is locked here and unlocked again.
 */
const LOCKOUT_USER = "login_lockout_user";
const LOCKOUT_USER_ID = 907;

const unlock = () => cy.makePrivateAdminAPICall("POST", `/admin/api/user/${LOCKOUT_USER_ID}/login/reset`, null, 200);

const lockOut = (failures) => {
    Cypress._.times(failures, () =>
        cy.apiRequest({
            method: "POST",
            url: "/api/public/user/login",
            headers: { "content-type": "application/json" },
            body: { userName: LOCKOUT_USER, password: "wrong_password" },
        }),
    );
};

describe("Admin: temporary account lockout (#10197)", () => {
    let savedLockoutMinutes;
    let maxFailedLogins;

    before(() => {
        cy.getSystemConfig("iLoginLockoutMinutes").then((value) => {
            savedLockoutMinutes = value;
        });
        cy.getSystemConfig("iMaxFailedLogins").then((value) => {
            maxFailedLogins = Number(value);
        });
    });

    afterEach(() => {
        cy.restoreSystemConfig("iLoginLockoutMinutes", savedLockoutMinutes);
        unlock();
    });

    it("the Users page offers the lockout settings", () => {
        cy.setupAdminSession();
        cy.visit("/admin/system/users");
        cy.get("#userSettingsPanel")
            .should("contain.text", "Lockout period in minutes")
            .and("contain.text", "Maximum number of failed logins from one IP address");
    });

    it("a locked account shows when it unlocks, and Reset Failed Logins unlocks it", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/iLoginLockoutMinutes", { value: "2" }, 200);
        unlock();
        lockOut(maxFailedLogins);

        cy.setupAdminSession();
        cy.visit(`/v2/user/${LOCKOUT_USER_ID}`);
        cy.get('[data-cy="lockout-minutes-left"]').should("contain.text", "Minutes until it unlocks by itself: 2");

        const filterToLockoutUser = () => {
            cy.get("#user-listing-table_wrapper input[type=search]").type(LOCKOUT_USER);
            cy.get("#user-listing-table tbody tr").should("have.length", 1).and("contain.text", "Login Lockout");
        };
        cy.visit("/admin/system/users");
        filterToLockoutUser();
        cy.get("#user-listing-table tbody tr .badge").contains("Locked").should("exist");
        cy.get('#user-listing-table tbody tr [data-bs-toggle="dropdown"]').click();
        cy.get("#user-listing-table tbody tr .js-reset-login-count").should("be.visible").click();
        cy.intercept("POST", `**/admin/api/user/${LOCKOUT_USER_ID}/login/reset`).as("reset");
        cy.get(".bootbox-accept").should("be.visible").click();
        cy.wait("@reset").its("response.statusCode").should("eq", 200);

        cy.visit("/admin/system/users");
        filterToLockoutUser();
        cy.get("#user-listing-table tbody tr").should("not.contain.text", "Locked");
    });
});
