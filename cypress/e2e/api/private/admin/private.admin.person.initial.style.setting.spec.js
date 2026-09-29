/// <reference types="cypress" />

/**
 * Person avatar behaviour after a photo is deleted, plus the
 * iPersonInitialStyle system setting.
 *
 * These tests used to delete person 2's photo. Person 2's photo *is* the
 * tracked fixture cypress/data/images/people/2.png: the dev and test compose
 * profiles bind-mount cypress/data/images/people as Images/Person, so the
 * delete removed a tracked file from the working tree and left `git status`
 * dirty after every run (issue #9777). It also silently broke any later spec
 * that expects person 2 to have a photo.
 *
 * The tests now upload a throwaway photo to a person with no tracked fixture
 * and delete that instead, and after() leaves no photo behind.
 */
describe("API Private Admin Person Initial Setting", () => {
    // Person 28 has no file under cypress/data/images/people, so nothing this
    // spec uploads or deletes can touch a tracked fixture.
    const testPersonId = 28;
    const PHOTO_URL = `/api/person/${testPersonId}/photo`;
    const AVATAR_URL = `/api/person/${testPersonId}/avatar`;
    // Smallest valid 1x1 PNG.
    const BASE64_PNG =
        "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";

    beforeEach(() => {
        // Give the person a photo of our own to delete.
        cy.makePrivateAdminAPICall(
            "POST",
            PHOTO_URL,
            JSON.stringify({ imgBase64: BASE64_PNG }),
            200,
        );
    });

    after(() => {
        // The delete is idempotent (200 with no photo present), so this is safe
        // whether or not a test left one behind.
        cy.makePrivateAdminAPICall("DELETE", PHOTO_URL, null, 200);
    });

    it("Delete Person Image / Avatar info available for client-side rendering", () => {
        cy.makePrivateAdminAPICall("DELETE", PHOTO_URL, null, 200);

        // After deleting photo, /photo endpoint returns 404 (no uploaded photo)
        cy.makePrivateAdminAPICall("GET", PHOTO_URL, null, 404);

        // Avatar info endpoint returns data for client-side rendering
        cy.makePrivateAdminAPICall("GET", AVATAR_URL, null, 200).then((resp) => {
            expect(resp.body).to.have.property("initials");
            expect(resp.body).to.have.property("hasPhoto");
            expect(resp.body.hasPhoto).to.eq(false);
        });
    });

    it("Change Person Initial Style / Delete Person Image / Avatar info available", () => {
        const json = { value: "1" };
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/iPersonInitialStyle",
            json,
            200,
        );

        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/config/iPersonInitialStyle",
            null,
            200,
        ).then((resp) => {
            expect(resp.body.value).to.eq(json.value);
        });

        cy.makePrivateAdminAPICall("DELETE", PHOTO_URL, null, 200);

        // After deleting photo, /photo endpoint returns 404
        cy.makePrivateAdminAPICall("GET", PHOTO_URL, null, 404);

        // Avatar info endpoint returns data for client-side rendering
        cy.makePrivateAdminAPICall("GET", AVATAR_URL, null, 200).then((resp) => {
            expect(resp.body).to.have.property("initials");
        });
    });
});
