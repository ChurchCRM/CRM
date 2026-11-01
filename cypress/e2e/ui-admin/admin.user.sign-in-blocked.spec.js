describe("Admin Users list - accounts that cannot sign in (#10193)", () => {
    const findUser = (loginName) => {
        cy.visit("admin/system/users");
        cy.get(".dt-search input").clear().type(loginName);
        return cy.contains("#user-listing-table tbody tr", loginName);
    };

    before(() => {
        ["deceased.user", "inactive.user"].forEach((userName) => {
            Cypress._.times(6, () => {
                cy.apiRequest({
                    method: "POST",
                    url: "/api/public/user/login",
                    headers: { "content-type": "application/json" },
                    body: { userName, password: "changeme" },
                });
            });
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("flags a deceased person's account", () => {
        findUser("deceased.user")
            .find('[data-cy="sign-in-blocked-badge"]')
            .should("have.attr", "data-sign-in-blocked", "deceased")
            .and("contain.text", "Deceased — cannot sign in");
    });

    it("flags an inactive person's account", () => {
        findUser("inactive.user")
            .find('[data-cy="sign-in-blocked-badge"]')
            .should("have.attr", "data-sign-in-blocked", "inactive")
            .and("contain.text", "Inactive — cannot sign in");
    });

    it("refused sign-ins do not count as failed logins", () => {
        findUser("deceased.user").find("td:nth-child(5)").should("contain.text", "—");
        findUser("inactive.user").find("td:nth-child(5)").should("contain.text", "—");
    });

    it("does not flag an ordinary active account", () => {
        findUser("deactivate.target").find('[data-cy="sign-in-blocked-badge"]').should("not.exist");
    });

    it("shows the reason on the single-user page", () => {
        cy.visit("v2/user/910");
        cy.get('[data-cy="sign-in-blocked-alert"]').should("contain.text", "Deceased — cannot sign in");
        cy.visit("v2/user/912");
        cy.get('[data-cy="sign-in-blocked-alert"]').should("not.exist");
    });
});
