/// <reference types="cypress" />

/**
 * The user editor refuses to remove administrator access from the only
 * administrator who can sign in (#10926).
 *
 * The seed has two administrators: Church Admin (person 1) and locale-admin (person 906).
 * Deactivating 906 makes person 1 the only administrator who can sign in. Both people
 * are reactivated through tony.wade's EditRecords key in after().
 */
describe("User Editor - last administrator guard", () => {
    const seededAdminId = 1;
    const secondAdminId = 906;

    const setActive = (personId, active) =>
        cy.makePrivateUserAPICall("POST", `/api/person/${personId}/activate/${active}`, null, 200);

    const saveAccessMode = (personId, mode) => {
        cy.then(() => Cypress.session.clearAllSavedSessions());
        cy.setupAdminSession();
        cy.intercept("POST", `**/admin/system/users/${personId}/edit*`).as("saveUser");
        cy.visit(`admin/system/users/${personId}/edit`);
        cy.contains("User Editor");
        cy.get(`input[name="accessMode"][value="${mode}"]`).check({ force: true });
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");
    };

    after(() => {
        setActive(seededAdminId, true);
        setActive(secondAdminId, true);
    });

    it("refuses to demote the only administrator who can sign in", () => {
        setActive(secondAdminId, false);

        saveAccessMode(seededAdminId, "custom");
        cy.get(".alert-danger").should("contain", "only administrator who can sign in");

        cy.visit(`admin/system/users/${seededAdminId}/edit`);
        cy.get('input[name="accessMode"][value="admin"]').should("be.checked");
    });

    it("allows demoting an administrator while another one can still sign in", () => {
        setActive(secondAdminId, true);

        saveAccessMode(secondAdminId, "custom");
        cy.visit(`admin/system/users/${secondAdminId}/edit`);
        cy.get('input[name="accessMode"][value="custom"]').should("be.checked");

        saveAccessMode(secondAdminId, "admin");
        cy.visit(`admin/system/users/${secondAdminId}/edit`);
        cy.get('input[name="accessMode"][value="admin"]').should("be.checked");
    });
});
