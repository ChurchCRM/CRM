/// <reference types="cypress" />

/**
 * #8672: the Users page can disable and enable a login's sign-in, and Delete
 * User says it removes the login only and can also disable sign-in, so the
 * person can't keep using the Member Portal.
 */
const PERSON = 62; // Emma Beck: no seeded login, used by no other spec
const tag = `signin${Date.now()}`;

const admin = (method, url, body = null, expectedStatus = 200) => cy.makePrivateAdminAPICall(method, url, body, expectedStatus);

const createLogin = () => {
    cy.setupAdminSession({ forceLogin: true });
    cy.visit(`admin/system/users/new?personId=${PERSON}`);
    cy.get("input[name=csrf_token]")
        .invoke("val")
        .then((csrfToken) =>
            cy.request({
                method: "POST",
                url: "/admin/system/users/new",
                form: true,
                body: { csrf_token: csrfToken, PersonID: PERSON, UserName: tag, accessMode: "custom", Notes: "1" },
            }),
        );
};

// Shows only this spec's row, whatever page DataTables put it on.
const visitUsersRow = () => {
    cy.setupAdminSession({ forceLogin: true });
    cy.visit("admin/system/users");
    cy.window().then((win) => win.$("#user-listing-table").DataTable().search(tag).draw());
    return cy.contains("#user-listing-table tbody tr", tag);
};

const openRowMenu = () => visitUsersRow().find("[data-bs-toggle=dropdown]").click();

const cleanUp = () => {
    admin("POST", `/admin/api/person/${PERSON}/sign-in/enable`, null, [200, 409]);
    admin("DELETE", `/admin/api/user/${PERSON}/`, null, [200, 404]);
};

describe("Users page sign-in actions and Delete User (#8672)", () => {
    beforeEach(() => {
        cleanUp();
        createLogin();
    });

    after(cleanUp);

    it("Delete User leaves sign-in alone unless asked", () => {
        admin("DELETE", `/admin/api/user/${PERSON}/`);
        // Sign-in was never disabled, so there is nothing to enable.
        admin("POST", `/admin/api/person/${PERSON}/sign-in/enable`, null, 409);
    });

    it("Delete User with disableSignIn also disables the person's sign-in", () => {
        admin("DELETE", `/admin/api/user/${PERSON}/`, { disableSignIn: true });
        admin("DELETE", `/admin/api/user/${PERSON}/`, null, 404);
        admin("POST", `/admin/api/person/${PERSON}/sign-in/enable`);
    });

    it("disables and enables sign-in from the row menu", () => {
        openRowMenu();
        cy.contains("#user-listing-table tbody tr", tag).find(".js-disable-sign-in").click();
        cy.get(".bootbox-prompt textarea").type("Left the staff");
        cy.get(".bootbox-prompt .bootbox-accept").click();

        visitUsersRow().find("[data-cy=sign-in-blocked-badge]").should("contain", "Sign-in disabled");
        cy.contains("#user-listing-table tbody tr", tag).find(".js-disable-sign-in").should("not.exist");

        openRowMenu();
        cy.contains("#user-listing-table tbody tr", tag).find(".js-enable-sign-in").click();
        cy.get(".bootbox-confirm .bootbox-accept").click();

        visitUsersRow().find("[data-cy=sign-in-blocked-badge]").should("not.exist");
        cy.contains("#user-listing-table tbody tr", tag).find(".js-disable-sign-in").should("exist");
    });

    it("the delete dialog says it removes the login only and disables sign-in by default", () => {
        openRowMenu();
        cy.contains("#user-listing-table tbody tr", tag).find(".js-delete-user").click();
        cy.get(".bootbox-confirm")
            .should("contain", "This removes the login only. The person and their notes stay.")
            .find("#delete-user-disable-sign-in")
            .should("be.checked");
        cy.get(".bootbox-confirm .bootbox-accept").click();

        cy.location("pathname").should("match", /\/admin\/system\/users$/);
        admin("DELETE", `/admin/api/user/${PERSON}/`, null, 404);
        // The person stays, with sign-in now disabled.
        admin("GET", `/api/person/${PERSON}`);
        admin("POST", `/admin/api/person/${PERSON}/sign-in/enable`);
    });
});
