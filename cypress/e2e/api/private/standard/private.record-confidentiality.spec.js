/// <reference types="cypress" />

/**
 * Protected households and confidential notes.
 *
 * Seed data: family 2 (Hart, persons 6-9) is flagged protected for the duration
 * of the F-01 block. Admin is person 1; tony.wade (makePrivateUserAPICall) is
 * person 3 in family 1 and holds Notes=1 + EditRecords.
 */
describe("Protected households", () => {
    const FAMILY = 2;

    before(() => {
        cy.makePrivateAdminAPICall("PUT", `/api/family/${FAMILY}/confidential`, { confidential: true }, 200);
    });

    after(() => {
        cy.makePrivateAdminAPICall("PUT", `/api/family/${FAMILY}/confidential`, { confidential: false }, 200);
    });

    it("does not let a non-member see or change the flag", () => {
        cy.makePrivateUserAPICall("PUT", `/api/family/${FAMILY}/confidential`, { confidential: false }, 404);
    });

    it("hides the family and its members from a non-member without disclosing they exist", () => {
        cy.makePrivateUserAPICall("GET", `/api/family/${FAMILY}/notes`, null, 404);
        cy.makePrivateUserAPICall("GET", "/api/person/6/notes", null, 404);
    });

    it("hides a public note on the household from a direct lookup by id", () => {
        cy.makePrivateAdminAPICall("POST", `/api/family/${FAMILY}/note`, { text: "<p>Household note</p>" }, 201).then((r) => {
            const id = r.body.note.id;
            cy.makePrivateAdminAPICall("GET", `/api/note/${id}`, null, 200);
            cy.makePrivateUserAPICall("GET", `/api/note/${id}`, null, 404);
            cy.makePrivateAdminAPICall("DELETE", `/api/note/${id}`, null, 200);
        });
    });

    it("keeps the family out of search and recent lists", () => {
        cy.makePrivateUserAPICall("GET", "/api/families/search/Hart", null, 200).then((r) => {
            expect(JSON.stringify(r.body)).to.not.include("Hart");
        });
        cy.makePrivateUserAPICall("GET", "/api/persons/search/Constance", null, 200).then((r) => {
            expect(JSON.stringify(r.body)).to.not.include("Hart");
        });
    });

    it("keeps the household and its members out of the latest, updated, birthday and anniversary lists", () => {
        [
            "/api/persons/latest",
            "/api/persons/updated",
            "/api/persons/birthday",
            "/api/families/latest",
            "/api/families/updated",
            "/api/families/anniversaries",
        ].forEach((url) => {
            cy.makePrivateUserAPICall("GET", url, null, 200).then((r) => {
                expect(JSON.stringify(r.body), url).to.not.include("Hart");
            });
        });
    });

    it("leaves the family fully visible to an admin", () => {
        cy.makePrivateAdminAPICall("GET", `/api/family/${FAMILY}/notes`, null, 200);
        cy.makePrivateAdminAPICall("GET", "/api/person/6/notes", null, 200);
        cy.makePrivateAdminAPICall("GET", "/api/families/search/Hart", null, 200).then((r) => {
            expect(JSON.stringify(r.body)).to.include("Hart");
        });
    });

    it("restores access when the flag is cleared", () => {
        cy.makePrivateAdminAPICall("PUT", `/api/family/${FAMILY}/confidential`, { confidential: false }, 200);
        cy.makePrivateUserAPICall("GET", "/api/person/6/notes", null, 200);
        cy.makePrivateUserAPICall("PUT", `/api/family/${FAMILY}/confidential`, { confidential: true }, 403);
        cy.makePrivateAdminAPICall("PUT", `/api/family/${FAMILY}/confidential`, { confidential: true }, 200);
    });
});

describe("Protected household: own members", () => {
    // Family 20 (Black) includes lena.black.editself.notes (person 100).
    const FAMILY = 20;

    before(() => {
        cy.makePrivateAdminAPICall("PUT", `/api/family/${FAMILY}/confidential`, { confidential: true }, 200);
    });

    after(() => {
        cy.makePrivateAdminAPICall("PUT", `/api/family/${FAMILY}/confidential`, { confidential: false }, 200);
    });

    it("lets a member of the household keep reading it while others get 404", () => {
        cy.makePrivateEditSelfPlusNotesAPICall("GET", `/api/family/${FAMILY}/notes`, null, 200);
        cy.makePrivateEditSelfPlusNotesAPICall("GET", "/api/person/100/notes", null, 200);
        cy.makePrivateUserAPICall("GET", `/api/family/${FAMILY}/notes`, null, 404);
    });
});

describe("Confidential notes", () => {
    const ADMIN_ID = 1;
    const created = [];

    const createAsUser = (body) =>
        cy.makePrivateUserAPICall("POST", "/api/person/2/note", { text: "<p>Pastoral note</p>", confidential: true, ...body }, 201).then((r) => {
            created.push(r.body.note.id);
            return r.body.note.id;
        });

    after(() => {
        created.forEach((id) => cy.makePrivateUserAPICall("DELETE", `/api/note/${id}`, null, 200));
    });

    it("rejects recipients that are not users", () => {
        cy.makePrivateUserAPICall("POST", "/api/person/2/note", { text: "<p>x</p>", confidential: true, recipientIds: [99999999] }, 400);
    });

    it("gives an admin who is not a recipient no read, edit, delete or enumeration access", () => {
        createAsUser({ recipientIds: [] }).then((noteId) => {
            cy.makePrivateUserAPICall("GET", `/api/note/${noteId}`, null, 200).then((r) => {
                expect(r.body.note.confidential).to.eq(true);
                expect(r.body.note.private).to.eq(true);
            });
            cy.makePrivateAdminAPICall("GET", `/api/note/${noteId}`, null, 404);
            cy.makePrivateAdminAPICall("PUT", `/api/note/${noteId}`, { text: "<p>tamper</p>" }, 404);
            cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 404);
            cy.makePrivateAdminAPICall("GET", "/api/person/2/notes", null, 200).then((r) => {
                expect(r.body.notes.map((n) => n.id)).to.not.include(noteId);
            });
            cy.makePrivateAdminAPICall("GET", "/api/timeline/person/2", null, 200).then((r) => {
                expect(r.body.timeline.map((i) => i.id)).to.not.include(String(noteId));
            });
        });
    });

    it("lets a named recipient read but not edit or delete, and the author revoke", () => {
        createAsUser({ recipientIds: [ADMIN_ID] }).then((noteId) => {
            cy.makePrivateAdminAPICall("GET", `/api/note/${noteId}`, null, 200);
            cy.makePrivateAdminAPICall("PUT", `/api/note/${noteId}`, { text: "<p>tamper</p>" }, 403);
            cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 403);
            cy.makePrivateAdminAPICall("PUT", `/api/note/${noteId}/recipients`, { recipientIds: [] }, 403);

            cy.makePrivateUserAPICall("PUT", `/api/note/${noteId}/recipients`, { recipientIds: [] }, 200);
            cy.makePrivateAdminAPICall("GET", `/api/note/${noteId}`, null, 404);
        });
    });

    it("keeps the note confidential when the author clears the private flag", () => {
        createAsUser({ recipientIds: [] }).then((noteId) => {
            cy.makePrivateUserAPICall("PUT", `/api/note/${noteId}`, { text: "<p>edited</p>", private: false }, 200).then((r) => {
                expect(r.body.note.confidential).to.eq(true);
                expect(r.body.note.private).to.eq(true);
            });
            cy.makePrivateAdminAPICall("GET", `/api/note/${noteId}`, null, 404);
        });
    });

    it("leaves ordinary private notes under the existing admin policy", () => {
        cy.makePrivateUserAPICall("POST", "/api/person/2/note", { text: "<p>private</p>", private: true }, 201).then((r) => {
            const id = r.body.note.id;
            created.push(id);
            expect(r.body.note.confidential).to.eq(false);
            cy.makePrivateAdminAPICall("GET", `/api/note/${id}`, null, 200);
        });
    });
});
