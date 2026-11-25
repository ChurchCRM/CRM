/// <reference types="cypress" />

/**
 * The user editor refuses to remove administrator access from the only
 * administrator who can sign in (#10926).
 *
 * Seeded sign-in-capable admins: Church Admin (1), locale-admin (906), and
 * ada.admin.nofinance (913). Deactivating every extra admin makes person 1 the
 * only one. They are reactivated through tony.wade's EditRecords key in after().
 */
describe("User Editor - last administrator guard", () => {
    const seededAdminId = 1;
    const otherAdminId = 906;
    const extraAdminIds = [906, 913];

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
        extraAdminIds.forEach((id) => setActive(id, true));
    });

    it("refuses to demote the only administrator who can sign in", () => {
        extraAdminIds.forEach((id) => setActive(id, false));

        saveAccessMode(seededAdminId, "custom");
        cy.get(".alert-danger").should("contain", "only administrator who can sign in");

        cy.visit(`admin/system/users/${seededAdminId}/edit`);
        cy.get('input[name="accessMode"][value="admin"]').should("be.checked");
    });

    it("allows demoting an administrator while another one can still sign in", () => {
        setActive(otherAdminId, true);

        saveAccessMode(otherAdminId, "custom");
        cy.visit(`admin/system/users/${otherAdminId}/edit`);
        cy.get('input[name="accessMode"][value="custom"]').should("be.checked");

        saveAccessMode(otherAdminId, "admin");
        cy.visit(`admin/system/users/${otherAdminId}/edit`);
        cy.get('input[name="accessMode"][value="admin"]').should("be.checked");
    });
});
