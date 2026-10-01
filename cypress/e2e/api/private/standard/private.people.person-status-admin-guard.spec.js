/// <reference types="cypress" />

/**
 * The last administrator who can sign in cannot be deactivated (#10193).
 *
 * The seed has two administrators: Church Admin (person 1) and locale-admin (person 906).
 * The spec deactivates 906 so person 1 becomes the only administrator who can sign in, then
 * tries to deactivate person 1 as tony.wade (EditRecords, not admin). Both people are
 * reactivated through tony's key in after(), so a regression that lets the deactivation
 * through cannot strand the suite without its admin key.
 */
describe("POST /api/person/{id}/activate/false - last administrator guard", () => {
    const seededAdminId = 1;
    const secondAdminId = 906;

    const setActive = (personId, active) =>
        cy.makePrivateUserAPICall("POST", `/api/person/${personId}/activate/${active}`, null, 200);

    before(() => {
        cy.makePrivateAdminAPICall("POST", `/api/person/${secondAdminId}/activate/false`, null, 200);
    });

    after(() => {
        setActive(seededAdminId, true);
        setActive(secondAdminId, true);
    });

    it("refuses to deactivate the only administrator who can sign in", () => {
        cy.makePrivateUserAPICall("POST", `/api/person/${seededAdminId}/activate/false`, null, 403);
        cy.makePrivateUserAPICall("GET", `/api/person/${seededAdminId}`, null, 200).then((response) => {
            expect(response.body.DateDeactivated ?? null).to.eq(null);
        });
    });

    it("allows deactivating an administrator while another one can still sign in", () => {
        setActive(secondAdminId, true);
        cy.makePrivateUserAPICall("POST", `/api/person/${secondAdminId}/activate/false`, null, 200);
    });
});
