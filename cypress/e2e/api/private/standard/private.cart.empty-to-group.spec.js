/// <reference types="cypress" />

/**
 * Regression coverage for ChurchCRM/CRM#8570 — `POST /api/cart/emptyToGroup`
 * was returning a confusing 400 ("Invalid request data") whenever the JS
 * caller forgot to send `groupRoleID`. The frontend bug was that
 * `JSON.stringify` silently drops keys whose value is `undefined`, so a
 * missing TomSelect read produced the same payload as deliberately omitting
 * the field. The API contract is that BOTH parameters are required and
 * must be numeric — these tests pin that contract down so a future
 * refactor cannot regress it without noticing.
 */
describe("API Private Cart - emptyToGroup", () => {
    // Test fixture group: "Clergy" (grp_ID = 11) — has role list 23 with
    // option_id 1 = "Member". See cypress/data/seed.sql.
    const TARGET_GROUP_ID = 11;
    const TARGET_ROLE_ID = 1;

    // Persons we move into the group during the happy-path test. These come
    // from family 6 (already used by private.people.family.cart.spec.js).
    const TEST_PERSON_IDS = [28, 30];

    beforeEach(() => {
        cy.setupStandardSession();

        // Make sure the cart starts empty so each test owns its own state.
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/cart/",
            null,
            200,
        );

        cy.on("uncaught:exception", () => false);
    });

    afterEach(() => {
        // Clean up: remove the test persons from the target group so the
        // happy-path test can be re-run safely. removeperson is idempotent
        // (the route loops membership rows and only deletes matches), so a
        // 200 is expected even if the person was never added.
        TEST_PERSON_IDS.forEach((personId) => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${TARGET_GROUP_ID}/removeperson/${personId}`,
                null,
                200,
            );
        });
    });

    it("returns 400 when groupRoleID is missing (regression for #8570)", () => {
        // This is exactly the broken payload the JS was sending: groupID
        // present, groupRoleID dropped by JSON.stringify because the
        // TomSelect read returned `undefined`.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({ groupID: TARGET_GROUP_ID }),
            400,
        );
    });

    it("returns 400 when groupID is missing", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({ groupRoleID: TARGET_ROLE_ID }),
            400,
        );
    });

    it("returns 400 when both fields are missing", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({}),
            400,
        );
    });

    it("returns 400 when groupID is non-numeric", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({ groupID: "not-a-number", groupRoleID: TARGET_ROLE_ID }),
            400,
        );
    });

    it("returns 400 when groupRoleID is non-numeric", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({ groupID: TARGET_GROUP_ID, groupRoleID: "not-a-number" }),
            400,
        );
    });

    it("treats an existing group membership as a successful assignment", () => {
        const personId = TEST_PERSON_IDS[0];

        // Add the person to the group for the first time.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/",
            JSON.stringify({ Persons: [personId] }),
            200,
        );

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({
                groupID: TARGET_GROUP_ID,
                groupRoleID: TARGET_ROLE_ID,
            }),
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("status", "success");
        });

        // Put the same person back into the cart and assign them again.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/",
            JSON.stringify({ Persons: [personId] }),
            200,
        );

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({
                groupID: TARGET_GROUP_ID,
                groupRoleID: TARGET_ROLE_ID,
            }),
            200,
        ).then((resp) => {
            expect(resp.body).to.include({
                status: "success",
                successCount: 1,
                failedCount: 0,
            });
            expect(resp.body.failedPeople).to.deep.equal([]);
        });

        cy.makePrivateAdminAPICall("GET", "/api/cart/", null, 200).then((resp) => {
            expect(resp.body.PeopleCart).to.be.an("array").that.is.empty;
        });

        cy.makePrivateAdminAPICall(
            "GET",
            `/api/groups/${TARGET_GROUP_ID}/members`,
            null,
            200,
        ).then((resp) => {
            const memberships = resp.body.Person2group2roleP2g2rs.filter(
                (member) => Number(member.PersonId) === personId,
            );
            expect(memberships).to.have.length(1);
        });
    });

    it("returns partial success and keeps failed people in the cart", () => {
        const validPersonId = TEST_PERSON_IDS[0];
        const invalidPersonId = 2147483647;

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/",
            JSON.stringify({ Persons: [validPersonId, invalidPersonId] }),
            200,
        );

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({
                groupID: TARGET_GROUP_ID,
                groupRoleID: TARGET_ROLE_ID,
            }),
            200,
        ).then((resp) => {
            expect(resp.body).to.include({
                status: "partial_success",
                successCount: 1,
                failedCount: 1,
            });
            expect(resp.body.failedPeople).to.deep.equal([invalidPersonId]);
        });

        cy.makePrivateAdminAPICall("GET", "/api/cart/", null, 200).then((resp) => {
            expect(resp.body.PeopleCart).to.deep.equal([invalidPersonId]);
        });

        cy.makePrivateAdminAPICall(
            "GET",
            `/api/groups/${TARGET_GROUP_ID}/members`,
            null,
            200,
        ).then((resp) => {
            const memberIds = resp.body.Person2group2roleP2g2rs.map(
                (member) => Number(member.PersonId),
            );
            expect(memberIds).to.include(validPersonId);
            expect(memberIds).not.to.include(invalidPersonId);
        });
        // Retry the remaining failed people.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({
                groupID: TARGET_GROUP_ID,
                groupRoleID: TARGET_ROLE_ID,
            }),
            200,
        ).then((resp) => {
            expect(resp.body.status).to.equal("partial_success");
            expect(resp.body.successCount).to.equal(0);
            expect(resp.body.failedCount).to.equal(1);
            expect(resp.body.failedPeople).to.deep.equal([invalidPersonId]);
        });

        // The successful person must not be added twice.
        cy.makePrivateAdminAPICall(
            "GET",
            `/api/groups/${TARGET_GROUP_ID}/members`,
            null,
            200,
        ).then((resp) => {
            const memberships = resp.body.Person2group2roleP2g2rs.filter(
                (member) => Number(member.PersonId) === validPersonId,
            );

            expect(memberships).to.have.length(1);
        });

        // Only the failed person should remain in the cart.
        cy.makePrivateAdminAPICall("GET", "/api/cart/", null, 200).then((resp) => {
            expect(resp.body.PeopleCart).to.deep.equal([invalidPersonId]);
        });

    });

    it("moves cart contents into the target group and empties the cart", () => {
        // Seed the cart with two people.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/",
            JSON.stringify({ Persons: TEST_PERSON_IDS }),
            200,
        );

        // Sanity-check the cart actually got populated.
        cy.makePrivateAdminAPICall("GET", "/api/cart/", null, 200).then((resp) => {
            expect(resp.body.PeopleCart).to.include.members(TEST_PERSON_IDS);
        });

        // Happy path — both required fields present.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/cart/emptyToGroup",
            JSON.stringify({
                groupID: TARGET_GROUP_ID,
                groupRoleID: TARGET_ROLE_ID,
            }),
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("status", "success");
        });

        // The cart should now be empty.
        cy.makePrivateAdminAPICall("GET", "/api/cart/", null, 200).then((resp) => {
            expect(resp.body.PeopleCart).to.be.an("array").that.has.lengthOf(0);
        });

        // Both persons should now be members of the target group.
        cy.makePrivateAdminAPICall(
            "GET",
            `/api/groups/${TARGET_GROUP_ID}/members`,
            null,
            200,
        ).then((resp) => {
            const memberIds = resp.body.Person2group2roleP2g2rs.map((m) => m.PersonId);
            TEST_PERSON_IDS.forEach((id) => {
                expect(memberIds).to.include(id);
            });
        });
    });
});
