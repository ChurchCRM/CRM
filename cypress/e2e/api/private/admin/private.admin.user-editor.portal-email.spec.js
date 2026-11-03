/// <reference types="cypress" />

// #10919: Member Portal login (accessMode "self") identifies the account by the
// person's personal email, so UserService::createUser() and ::updateUser()
// refuse it while another person shares that address. Other access modes are
// not affected.
//
// Persons 114, 123 and 162 (Boby Hall) share boby@example.com in
// cypress/data/seed.sql and have no user_usr row.
describe("API Private Admin User Editor - shared email blocks Member Portal login (#10919)", () => {
    const personId = 114;
    const sharedEmail = "boby@example.com";

    before(() => {
        cy.rememberTestEnv(["admin.api.key"]);
    });

    function postAdminForm(url, body) {
        return cy.request({
            method: "POST",
            url,
            failOnStatusCode: false,
            followRedirect: false,
            withCredentials: false,
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.testEnv("admin.api.key"),
            },
            body,
        });
    }

    function cleanupUser() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${personId}/`, null, [200, 204, 404]);
    }

    function expectDuplicateEmailError(resp) {
        expect(resp.status).to.eq(200);
        expect(resp.body).to.include(sharedEmail);
        expect(resp.body).to.include("Boby Hall");
        expect(resp.body).to.include("Member Portal login cannot be enabled");
    }

    beforeEach(() => {
        cleanupUser();
    });

    after(() => {
        cleanupUser();
    });

    it("blocks creating a Member Portal login for a person whose email is shared", () => {
        postAdminForm("/admin/system/users/new", {
            PersonID: personId,
            UserName: "boby.portal.new",
            accessMode: "self",
        }).then(expectDuplicateEmailError);

        cy.makePrivateAdminAPICall("GET", `/admin/system/users/${personId}/edit`, null, [200, 302, 404]).then((resp) => {
            expect(resp.body).to.not.include("boby.portal.new");
        });
    });

    it("still creates a user with another access mode when the email is shared", () => {
        postAdminForm("/admin/system/users/new", {
            PersonID: personId,
            UserName: "boby.custom.new",
            accessMode: "custom",
        }).then((resp) => {
            expect(resp.status).to.eq(302);
            expect(resp.headers.location).to.include("/admin/system/users");
        });
    });

    it("blocks switching an existing user to Member Portal login when the email is shared", () => {
        postAdminForm("/admin/system/users/new", {
            PersonID: personId,
            UserName: "boby.custom.edit",
            accessMode: "custom",
        }).then((resp) => {
            expect(resp.status).to.eq(302);
        });

        postAdminForm(`/admin/system/users/${personId}/edit`, {
            UserName: "boby.custom.edit",
            accessMode: "self",
        }).then(expectDuplicateEmailError);
    });
});
