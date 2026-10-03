/// <reference types="cypress" />

/**
 * Deleting a person also deletes their login, so DELETE /api/person/{id} and
 * DELETE /api/family/{id}?deleteMembers=true must refuse when the signed-in
 * user may not remove that login.
 *
 * Browser-form steps run in before(), ahead of the first cy.request(): an
 * x-api-key call flips the shared cookie jar's PHP session to API auth, so a
 * later cy.visit() would land on the login page.
 */
describe("API People - deleting a person with a login is guarded", () => {
    const tag = `LoginGuard${Date.now()}`;
    const createdPersonIds = [];
    const createdFamilyIds = [];
    const ids = {};
    before(() => {
        cy.rememberTestEnv(["admin.api.key"]);
    });

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
            createdPersonIds.push(personId);
            ids[key] = personId;
        });
    };

    const createFamilyWithMember = (memberFirstName) => {
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type(`Fam${tag}`);
        cy.get('input[name="FirstName1"]').type(memberFirstName);
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('button[name="FamilySubmit"]').click();
        cy.location("pathname").then((pathname) => {
            const familyId = Number.parseInt(pathname.split("/").pop(), 10);
            expect(familyId, "family created via FamilyEditor").to.be.greaterThan(0);
            createdFamilyIds.push(familyId);
            ids.family = familyId;
        });
    };

    before(() => {
        cy.setupStandardSession();
        createPerson(`Plain${tag}`, "plain");
        createFamilyWithMember(`Login${tag}`);

        cy.then(() => {
            cy.makePrivateAdminAPICall("GET", `/api/persons/search/Login${tag}`, null, 200).then(
                (resp) => {
                    expect(resp.body).to.be.an("array").and.to.have.length(1);
                    ids.adminMember = resp.body[0].objid;
                    createdPersonIds.push(ids.adminMember);
                },
            );
        });

        cy.then(() => {
            cy.request({
                method: "POST",
                url: "/admin/system/users/new",
                followRedirect: false,
                withCredentials: false,
                headers: {
                    "content-type": "application/json",
                    "x-api-key": Cypress.testEnv("admin.api.key"),
                },
                body: {
                    PersonID: ids.adminMember,
                    UserName: `lg${Date.now()}`,
                    accessMode: "admin",
                },
            })
                .its("status")
                .should("eq", 302);
        });
    });

    after(() => {
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

    it("refuses a DeleteRecords non-admin deleting an administrator's person", () => {
        cy.makePrivateUserAPICall("DELETE", `/api/person/${ids.adminMember}`, null, 403).then(
            (resp) => {
                expect(JSON.stringify(resp.body)).to.match(/administrator/i);
            },
        );
        cy.makePrivateAdminAPICall("GET", `/api/person/${ids.adminMember}`, null, 200);
    });

    it("refuses an administrator deleting themselves", () => {
        cy.makePrivateAdminAPICall("DELETE", "/api/person/1", null, 403).then((resp) => {
            expect(JSON.stringify(resp.body)).to.match(/yourself/i);
        });
        cy.makePrivateAdminAPICall("GET", "/api/person/1", null, 200);
    });

    it("refuses deleting a family with members when a member has a login, and deletes nothing", () => {
        cy.makePrivateUserAPICall(
            "DELETE",
            `/api/family/${ids.family}?deleteMembers=true`,
            null,
            403,
        ).then((resp) => {
            expect(JSON.stringify(resp.body)).to.match(/administrator/i);
        });
        cy.makePrivateAdminAPICall("GET", `/api/family/${ids.family}`, null, 200);
        cy.makePrivateAdminAPICall("GET", `/api/person/${ids.adminMember}`, null, 200);
    });

    it("still lets a DeleteRecords non-admin delete a person who has no login", () => {
        cy.makePrivateUserAPICall("DELETE", `/api/person/${ids.plain}`, null, 200);
        cy.makePrivateAdminAPICall("GET", `/api/person/${ids.plain}`, null, 404);
    });

    it("lets an administrator delete a family whose member has a login when another administrator exists", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            `/api/family/${ids.family}?deleteMembers=true`,
            null,
            200,
        );
        cy.makePrivateAdminAPICall("GET", `/api/family/${ids.family}`, null, 404);
        cy.makePrivateAdminAPICall("GET", `/api/person/${ids.adminMember}`, null, 404);
    });
});
