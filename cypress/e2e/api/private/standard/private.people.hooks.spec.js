/// <reference types="cypress" />

/**
 * Regression cover for the person/family plugin-hook dispatch points changed
 * in #9768.
 *
 * PERSON_UPDATED / FAMILY_UPDATED are dispatched from Person::postUpdate() /
 * Family::postUpdate() with an $oldData snapshot taken in preUpdate(), and
 * PERSON_DELETED / FAMILY_DELETED moved out of the API delete routes into
 * Person::postDelete() / Family::postDelete(), with the payload snapshotted in
 * preDelete() before the child-row cascade. Those Propel callbacks sit directly
 * in the save/delete path of every person and family, so a mistake there breaks
 * people writes outright rather than just the hook.
 *
 * This spec drives every path that reaches one of those dispatch points — the
 * legacy MVC editor forms (PersonEditor.php / FamilyEditor.php), the API role
 * and activate routes, DELETE /api/person/{id}, and the member cascade in
 * DELETE /api/family/{id}?deleteMembers=true — and asserts the write landed.
 *
 * Structure note: every browser-form step runs in before(), ahead of the first
 * cy.request(). cy.request() shares the browser cookie jar, so an x-api-key
 * call made mid-spec flips the PHP session to APITokenAuthentication and any
 * later cy.visit() lands on the login page. The same ordering is used by
 * private.people.family.delete-image-cleanup.spec.js.
 *
 * What this spec deliberately does NOT assert: that a plugin callback ran.
 * There is no hook-observability endpoint (HookManager::didAction() counts are
 * per-PHP-request and nothing exposes them), no existing Cypress pattern for
 * asserting a hook fired, and no test plugin in the repo. That half of #9768
 * was verified by registering person and family listeners and reading the app
 * log — the procedure and its output are recorded in the commit body.
 */
describe("API People Hooks - dispatch path regression", () => {
    const tag = `HookPpl${Date.now()}`;
    const renamed = `Renamed${tag}`;

    // Everything this spec creates, so after() can remove it again (#9769).
    const createdPersonIds = [];
    const createdFamilyIds = [];

    const ids = {};

    /** Create a family-less person through the legacy MVC editor form. */
    const createPerson = (firstName, key) => {
        cy.visit("/PersonEditor.php");
        cy.get("#FirstName").type(firstName);
        cy.get("#LastName").type(tag);
        cy.get("#Gender").select("1");
        cy.get("#Classification").select("1");
        cy.get("button[name='PersonSubmit']").click();
        cy.url().should("match", /people\/view\/\d+/);
        cy.url().then((url) => {
            const personId = Number.parseInt(url.match(/\/people\/view\/(\d+)/)[1], 10);
            expect(personId, "person created via PersonEditor").to.be.greaterThan(0);
            createdPersonIds.push(personId);
            ids[key] = personId;
        });
    };

    /** Create a family with one member through the legacy MVC editor form. */
    const createFamilyWithMember = (suffix, key) => {
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type(`Fam${tag}${suffix}`);
        cy.get('input[name="FirstName1"]').type(`${tag}${suffix}`);
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname").then((pathname) => {
            const familyId = Number.parseInt(pathname.split("/").pop(), 10);
            expect(familyId, "family created via FamilyEditor").to.be.greaterThan(0);
            createdFamilyIds.push(familyId);
            ids[key] = familyId;
        });
    };

    before(() => {
        cy.setupStandardSession();

        createPerson(`Upd${tag}`, "personUpd");
        createPerson(`Del${tag}`, "personDel");
        createFamilyWithMember("A", "familyA");
        createFamilyWithMember("B", "familyB");

        // MVC update path — Person::postUpdate() fires from the legacy editor
        // save, the same callback the hook dispatch now hangs off.
        cy.then(() => {
            cy.visit(`/PersonEditor.php?PersonID=${ids.personUpd}`);
            cy.get("#FirstName").clear().type(renamed);
            cy.get("button[name='PersonSubmit']").click();
            cy.url().should("match", /people\/view\/\d+/);
        });

        // Resolve the two family members by their unique first names. This is
        // the first cy.request() of the spec — no cy.visit() may follow it.
        cy.then(() => {
            ["A", "B"].forEach((suffix) => {
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/persons/search/${tag}${suffix}`,
                    null,
                    200,
                ).then((resp) => {
                    expect(resp.body).to.be.an("array").and.to.have.length.greaterThan(0);
                    const personId = resp.body[0].objid;
                    createdPersonIds.push(personId);
                    ids[`member${suffix}`] = personId;
                });
            });
        });
    });

    after(() => {
        // Leave the database exactly as the spec found it.
        createdFamilyIds.forEach((familyId) => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/family/${familyId}?deleteMembers=true`,
                null,
                [200, 404],
            );
        });
        createdPersonIds.forEach((personId) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, [200, 404]);
        });
    });

    it("Persists a person update made through the MVC editor", () => {
        cy.makePrivateAdminAPICall("GET", `/api/person/${ids.personUpd}`, null, 200).then(
            (resp) => {
                expect(resp.body.FirstName, "MVC update persisted").to.equal(renamed);
            },
        );
    });

    it("Persists a person update made through the API role route", () => {
        cy.makePrivateAdminAPICall("POST", `/api/person/${ids.personUpd}/role/3`, null, 200)
            .its("body.success")
            .should("eq", true);

        cy.makePrivateAdminAPICall("GET", `/api/person/${ids.personUpd}`, null, 200).then(
            (resp) => {
                expect(resp.body.FmrId, "API role update persisted").to.equal(3);
            },
        );
    });

    it("Deletes a person through the API", () => {
        cy.makePrivateAdminAPICall("DELETE", `/api/person/${ids.personDel}`, null, 200)
            .its("body.success")
            .should("eq", true);

        cy.makePrivateAdminAPICall("GET", `/api/person/${ids.personDel}`, null, 404);
    });

    it("Persists a family update made through the API activate route", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/family/${ids.familyA}/activate/false`,
            null,
            200,
        )
            .its("body.success")
            .should("eq", true);

        cy.makePrivateAdminAPICall("GET", `/api/family/${ids.familyA}`, null, 200).then(
            (resp) => {
                expect(resp.body.DateDeactivated, "deactivate persisted").to.not.be.null;
            },
        );

        cy.makePrivateAdminAPICall(
            "POST",
            `/api/family/${ids.familyA}/activate/true`,
            null,
            200,
        );

        cy.makePrivateAdminAPICall("GET", `/api/family/${ids.familyA}`, null, 200).then(
            (resp) => {
                expect(resp.body.DateDeactivated, "reactivate persisted").to.be.null;
            },
        );
    });

    it("Deletes a family and cascades to its members", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            `/api/family/${ids.familyB}?deleteMembers=true`,
            null,
            200,
        )
            .its("body.success")
            .should("eq", true);

        // The family row and the cascaded member row are both gone. The
        // member's removal runs through Person::postDelete(), which is the path
        // the old route-level PERSON_DELETED dispatch never reached.
        cy.makePrivateAdminAPICall("GET", `/api/family/${ids.familyB}`, null, 404);
        cy.makePrivateAdminAPICall("GET", `/api/person/${ids.memberB}`, null, 404);
    });
});
