/// <reference types="cypress" />

// Issue #10681: text-only updates must not disclose private notes; system
// timeline/audit rows must not be editable or erasable through notes CRUD.
describe("Notes privacy and audit immutability", () => {
    it("keeps an author's private note private on text-only update", () => {
        cy.makePrivateAdminAPICall("POST", "/api/person/2/note",
            { text: "<p>Private record to protect</p>", private: true }, 201)
            .then(({ body }) => {
                const id = body.note.id;
                cy.makePrivateAdminAPICall("PUT", `/api/note/${id}`,
                    { text: "<p>Updated but still private</p>" }, 200)
                    .then(({ body: updated }) => {
                        expect(updated.note.private).to.equal(true);
                        expect(updated.note.text).to.include("Updated but still private");
                    });
                // A different non-admin author cannot read that note.
                cy.makePrivateUserAPICall("GET", `/api/note/${id}`, null, 404);
                // Explicit false is still the supported intentional declassification.
                cy.makePrivateAdminAPICall("PUT", `/api/note/${id}`,
                    { text: "<p>Intentionally public</p>", private: false }, 200)
                    .then(({ body: updated }) => expect(updated.note.private).to.equal(false));
                cy.makePrivateUserAPICall("GET", `/api/note/${id}`, null, 200);
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${id}`, null, 200);
            });
    });

    it("rejects updating or deleting a seeded system audit note without changing it", () => {
        // The seeded first timeline row is a system 'create' note, not
        // an ordinary user note. It is read-only even to administrators.
        cy.makePrivateAdminAPICall("GET", "/api/note/1", null, 200).then(({ body }) => {
            const original = body.note;
            expect(original.type).to.not.equal("note");
            cy.makePrivateAdminAPICall("PUT", "/api/note/1",
                { text: "<p>Do not rewrite historical audit evidence</p>", private: false }, 403);
            cy.makePrivateAdminAPICall("DELETE", "/api/note/1", null, 403);
            cy.makePrivateAdminAPICall("GET", "/api/note/1", null, 200)
                .then(({ body: after }) => {
                    expect(after.note.type).to.equal(original.type);
                    expect(after.note.text).to.equal(original.text);
                });
        });
    });
});
