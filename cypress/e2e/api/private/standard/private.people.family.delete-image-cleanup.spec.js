/// <reference types="cypress" />

import { resetDeletionFixtureAudit, resetFamilyDeletionFixtureAudit } from "../../../../support/person-deletion-fixtures";

/**
 * API regression test for GH #1697 — deleting a family with its members
 * must remove the uploaded image files from disk for BOTH the family
 * and each deleted member.
 *
 * The `GET /api/family/{id}/photo` and `GET /api/person/{id}/photo`
 * endpoints only touch the filesystem (no DB lookup, no middleware that
 * requires the record to still exist), so a 200 response after delete
 * means the image file was leaked.
 *
 * Family + member are created through the legacy people/family/editor form
 * because there is no family-create JSON endpoint. The fixture utility models
 * legacy photos without audit notes; deletion and photo checks use the API.
 */
describe("API Private - Family delete cleans up image files (#1697)", () => {
    // Minimal valid 1×1 transparent PNG
    const base64Photo =
        "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";
    const uniqueTag = `DelImg${Date.now()}`;

    let familyId;
    let personId;

    before(() => {
        cy.setupStandardSession();

        // Create a temp family with one member via the legacy editor form.
        cy.visit("/people/family/editor");
        cy.get("#FamilyName").type(`Fam${uniqueTag}`);
        cy.get('input[name="firstName1"]').type(uniqueTag);
        cy.get('select[name="classification1"]').select("1", { force: true });
        cy.get('button[name="familySubmit"]').click();

        cy.location("pathname")
            .should("include", "/people/family/")
            .then((pathname) => {
                familyId = Number.parseInt(pathname.split("/").pop(), 10);
                expect(familyId).to.be.greaterThan(0);
            });

        // Resolve the member's id via the persons search endpoint — the
        // unique first name guarantees a single hit.
        cy.then(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/persons/search/${uniqueTag}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array").and.to.have.length.greaterThan(0);
                personId = resp.body[0].objid;
                expect(personId).to.be.greaterThan(0);
            });
        });
    });

    // The test deletes the family itself; this only runs the cleanup when a
    // step before that delete failed, so nothing is left in the test database.
    after(() => {
        // Clean up only the family/person created by this spec. A failed
        // assertion may leave upload audit notes behind, which otherwise
        // block the cleanup helper's deletion request.
        if (personId) {
            cy.then(() => resetDeletionFixtureAudit(personId, "photo"));
        }
        if (familyId) {
            cy.then(() => resetFamilyDeletionFixtureAudit(familyId, "photo"));
            cy.cleanupFamilies([familyId]);
        }
    });

    it("preserves photos with audit history and cleans up photos for a no-history fixture", () => {
        // 1. Upload photos for family and member.
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/family/${familyId}/photo`,
            JSON.stringify({ imgBase64: base64Photo }),
            200,
        )
            .its("body.hasPhoto")
            .should("eq", true);

        cy.makePrivateAdminAPICall(
            "POST",
            `/api/person/${personId}/photo`,
            JSON.stringify({ imgBase64: base64Photo }),
            200,
        )
            .its("body.hasPhoto")
            .should("eq", true);

        // 2. Sanity-check: both photos are fetchable before delete.
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/photo`, null, 200);
        cy.makePrivateAdminAPICall("GET", `/api/person/${personId}/photo`, null, 200);

        cy.makePrivateAdminAPICall(
            "DELETE",
            `/api/family/${familyId}?deleteMembers=true`,
            null,
            403,
        ).its("body.message").should("include", "Deactivate the family instead");
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200);
        cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200);
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/photo`, null, 200);
        cy.makePrivateAdminAPICall("GET", `/api/person/${personId}/photo`, null, 200);

        // Preserve the original cleanup regression for legacy photos without audit entries.
        cy.then(() => resetDeletionFixtureAudit(personId, "photo"));
        cy.then(() => resetFamilyDeletionFixtureAudit(familyId, "photo"));
        cy.makePrivateAdminAPICall(
            "DELETE",
            `/api/family/${familyId}?deleteMembers=true`,
            null,
            200,
        )
            .its("body.success")
            .should("eq", true);

        // 4. Both photo endpoints must now 404 — they only consult the
        // filesystem, so a 200 here would indicate a leaked image file.
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/photo`, null, 404);
        cy.makePrivateAdminAPICall("GET", `/api/person/${personId}/photo`, null, 404);
    });
});
