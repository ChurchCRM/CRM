/// <reference types="cypress" />

/**
 * API tests for Notes CRUD endpoints
 *
 * Tests cover:
 *   GET  /api/person/{id}/notes
 *   POST /api/person/{id}/note
 *   GET  /api/family/{id}/notes
 *   POST /api/family/{id}/note
 *   GET  /api/note/{id}
 *   PUT  /api/note/{id}
 *   DELETE /api/note/{id}
 */
describe("API Private Notes", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // Person notes
    // -----------------------------------------------------------------------
    describe("GET /api/person/{personId}/notes - List notes for a person", () => {
        it("Returns 200 with notes array for existing person", () => {
            cy.makePrivateAdminAPICall("GET", "/api/person/1/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });

        it("Returns 404 for non-existent person", () => {
            cy.makePrivateAdminAPICall("GET", "/api/person/99999/notes", null, 404);
        });
    });

    describe("POST /api/person/{personId}/note - Create note for person", () => {
        it("Creates a public note for a person and returns 201 with note object", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Cypress test note for person</p>", private: false },
                201,
            ).then((response) => {
                expect(response.body).to.have.property("note");
                const note = response.body.note;
                expect(note).to.have.property("id");
                expect(note.id).to.be.a("number");
                expect(note).to.have.property("perId", 1);
                expect(note).to.have.property("famId", 0);
                expect(note).to.have.property("private", false);
                expect(note).to.have.property("type", "note");
                expect(note).to.have.property("text");
                expect(note).to.have.property("dateEntered");
                expect(note.dateLastEdited).to.be.null;

                // Clean up — delete the note we just created
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${note.id}`, null, 200).then(
                    (del) => {
                        expect(del.body).to.have.property("success", true);
                    },
                );
            });
        });

        it("Creates a private note for a person and returns 201", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Private person note</p>", private: true },
                201,
            ).then((response) => {
                const note = response.body.note;
                expect(note).to.have.property("private", true);

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${note.id}`, null, 200);
            });
        });

        it("Returns 400 when note text is empty", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "", private: false },
                400,
            );
        });

        it("Returns 404 for non-existent person", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/99999/note",
                { text: "<p>test</p>", private: false },
                404,
            );
        });
    });

    // -----------------------------------------------------------------------
    // Family notes
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/notes - List notes for a family", () => {
        it("Returns 200 with notes array for existing family", () => {
            cy.makePrivateAdminAPICall("GET", "/api/family/1/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });

        it("Returns 404 for non-existent family", () => {
            cy.makePrivateAdminAPICall("GET", "/api/family/99999/notes", null, 404);
        });
    });

    describe("POST /api/family/{familyId}/note - Create note for family", () => {
        it("Creates a public note for a family and returns 201 with note object", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>Cypress test note for family</p>", private: false },
                201,
            ).then((response) => {
                expect(response.body).to.have.property("note");
                const note = response.body.note;
                expect(note).to.have.property("id");
                expect(note.id).to.be.a("number");
                expect(note).to.have.property("perId", 0);
                expect(note).to.have.property("famId", 1);
                expect(note).to.have.property("private", false);
                expect(note).to.have.property("type", "note");

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${note.id}`, null, 200).then(
                    (del) => {
                        expect(del.body).to.have.property("success", true);
                    },
                );
            });
        });

        it("Returns 400 when note text is empty", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/family/1/note",
                { text: "", private: false },
                400,
            );
        });
    });

    // -----------------------------------------------------------------------
    // Single note  GET / PUT / DELETE
    // -----------------------------------------------------------------------
    describe("GET /api/note/{noteId} - Get a single note", () => {
        it("Returns 404 for non-existent note", () => {
            cy.makePrivateAdminAPICall("GET", "/api/note/99999", null, 404);
        });

        it("Returns 200 with note object for an existing note", () => {
            // Create a note first, then fetch it
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Get single note test</p>", private: false },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateAdminAPICall("GET", `/api/note/${noteId}`, null, 200).then(
                    (response) => {
                        expect(response.body).to.have.property("note");
                        expect(response.body.note).to.have.property("id", noteId);
                        expect(response.body.note).to.have.property("type", "note");
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    describe("PUT /api/note/{noteId} - Update a note", () => {
        it("Returns 404 for non-existent note", () => {
            cy.makePrivateAdminAPICall(
                "PUT",
                "/api/note/99999",
                { text: "<p>update</p>", private: false },
                404,
            );
        });

        it("Updates text and private flag and returns updated note", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Original text</p>", private: false },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/note/${noteId}`,
                    { text: "<p>Updated text</p>", private: true },
                    200,
                ).then((updateResp) => {
                    expect(updateResp.body).to.have.property("note");
                    const updated = updateResp.body.note;
                    expect(updated).to.have.property("id", noteId);
                    expect(updated).to.have.property("private", true);
                    expect(updated.dateLastEdited).to.not.be.null;
                });

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Returns 400 when updating with empty text", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Will fail update</p>", private: false },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/note/${noteId}`,
                    { text: "", private: false },
                    400,
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    describe("DELETE /api/note/{noteId} - Delete a note", () => {
        it("Returns 404 for non-existent note", () => {
            cy.makePrivateAdminAPICall("DELETE", "/api/note/99999", null, 404);
        });

        it("Creates and then deletes a note successfully", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>Note to be deleted</p>", private: false },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200).then(
                    (deleteResp) => {
                        expect(deleteResp.body).to.have.property("success", true);
                    },
                );

                // Confirm it's gone
                cy.makePrivateAdminAPICall("GET", `/api/note/${noteId}`, null, 404);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Access control: non-admin user (has Notes permission, is not admin)
    // -----------------------------------------------------------------------
    describe("Access control - non-admin user with Notes permission", () => {
        it("Non-admin user can list person notes (returns 200)", () => {
            cy.makePrivateUserAPICall("GET", "/api/person/1/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });

        it("Private note created by admin is hidden from non-admin user", () => {
            // Admin creates a private note
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Admin private note</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Non-admin cannot fetch the private note directly
                cy.makePrivateUserAPICall("GET", `/api/note/${noteId}`, null, 404);

                // Non-admin list should not include this private note
                cy.makePrivateUserAPICall("GET", "/api/person/1/notes", null, 200).then(
                    (listResp) => {
                        const ids = listResp.body.notes.map((n) => n.id);
                        expect(ids).to.not.include(noteId);
                    },
                );

                // Clean up
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Non-owner gets 403 when trying to PUT admin's note", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Admin note for PUT test</p>", private: false },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateUserAPICall(
                    "PUT",
                    `/api/note/${noteId}`,
                    { text: "<p>Attempted edit</p>", private: false },
                    403,
                );

                // Clean up
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Non-owner gets 403 when trying to DELETE admin's note", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Admin note for DELETE test</p>", private: false },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateUserAPICall("DELETE", `/api/note/${noteId}`, null, 403);

                // Clean up
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Returns 401 when no API key is provided", () => {
            cy.request({
                method: "GET",
                url: "/api/person/1/notes",
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });
    });
});
