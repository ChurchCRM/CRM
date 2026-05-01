/// <reference types="cypress" />

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
 * Family + member are created through the legacy FamilyEditor.php form
 * because there is no family-create JSON endpoint; every other step is
 * an API call.
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
        cy.visit("FamilyEditor.php");
        cy.get("#FamilyName").type(`Fam${uniqueTag}`);
        cy.get('input[name="FirstName1"]').type(uniqueTag);
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('button[name="FamilySubmit"]').click();

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

    it("removes family AND member image files on delete", () => {
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

        // 3. Delete family including members.
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
