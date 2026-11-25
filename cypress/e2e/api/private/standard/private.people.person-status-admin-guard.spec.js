/// <reference types="cypress" />

/**
 * The last administrator who can sign in cannot be deactivated (#10193).
 *
 * Seeded sign-in-capable admins: Church Admin (1), locale-admin (906), and
 * ada.admin.nofinance (913). The spec deactivates every extra admin so person 1
 * is the only one, then tries to deactivate person 1 as tony.wade (EditRecords,
 * not admin). They are reactivated through tony's key in after(), so a
 * regression that lets the deactivation through cannot strand the suite
 * without its admin key.
 */
describe("POST /api/person/{id}/activate/false - last administrator guard", () => {
    const seededAdminId = 1;
    const otherAdminId = 906;
    const extraAdminIds = [906, 913];

    const setActive = (personId, active) =>
        cy.makePrivateUserAPICall("POST", `/api/person/${personId}/activate/${active}`, null, 200);

    before(() => {
        extraAdminIds.forEach((id) =>
            cy.makePrivateAdminAPICall("POST", `/api/person/${id}/activate/false`, null, 200),
        );
    });

    after(() => {
        setActive(seededAdminId, true);
        extraAdminIds.forEach((id) => setActive(id, true));
    });

    it("refuses to deactivate the only administrator who can sign in", () => {
        cy.makePrivateUserAPICall("POST", `/api/person/${seededAdminId}/activate/false`, null, 403);
        cy.makePrivateUserAPICall("GET", `/api/person/${seededAdminId}`, null, 200).then((response) => {
            expect(response.body.DateDeactivated ?? null).to.eq(null);
        });
    });

    it("allows deactivating an administrator while another one can still sign in", () => {
        setActive(otherAdminId, true);
        cy.makePrivateUserAPICall("POST", `/api/person/${otherAdminId}/activate/false`, null, 200);
    });
});
