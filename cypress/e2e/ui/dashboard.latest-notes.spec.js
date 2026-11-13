/// <reference types="cypress" />

describe("Dashboard latest notes", () => {
    const stamp = Date.now();
    const text = {
        publicPerson: `Visited after surgery ${stamp}`,
        publicFamily: `Moving in spring ${stamp}`,
        adminPrivate: `Pastoral concern ${stamp}`,
        userPrivate: `Follow-up reminder ${stamp}`,
    };
    const noteIds = [];
    const record = (apiCall, path, body) =>
        apiCall("POST", path, body, 201).then(({ body: created }) => noteIds.push(created.note.id));

    before(() => {
        const admin = (...args) => cy.makePrivateAdminAPICall(...args);
        const staff = (...args) => cy.makePrivateUserAPICall(...args);
        record(admin, "/api/person/2/note", { text: `<p>${text.publicPerson}</p><p>&amp; <b>bold</b></p>`, private: false });
        record(admin, "/api/family/2/note", { text: `<p>${text.publicFamily}</p>`, private: false });
        record(admin, "/api/person/2/note", { text: `<p>${text.adminPrivate}</p>`, private: true });
        record(staff, "/api/person/2/note", { text: `<p>${text.userPrivate}</p>`, private: true });
    });

    after(() => {
        cy.cleanupNotes(noteIds);
    });

    it("shows an admin every new note, newest first, as plain text", () => {
        cy.setupAdminSession();
        cy.visit("/v2/dashboard");
        cy.get("#latestNotesList .list-group-item").should("have.length.at.most", 5);
        [text.userPrivate, text.adminPrivate, text.publicFamily, text.publicPerson].forEach((note, index) => {
            cy.get("#latestNotesList .list-group-item").eq(index).should("contain.text", note);
        });
        cy.get("#latestNotesList")
            .contains(".list-group-item", text.publicPerson)
            .should("contain.text", "& bold")
            .and("have.attr", "href")
            .and("match", /\/people\/view\/2$/);
        cy.get("#latestNotesList")
            .contains(".list-group-item", text.publicFamily)
            .should("contain.text", "Hart")
            .and("have.attr", "href")
            .and("match", /\/people\/family\/2$/);
        cy.get("#latestNotesList b").should("not.exist");
    });

    it("hides other users' private notes from staff with notes access", () => {
        cy.setupStandardSession();
        cy.visit("/v2/dashboard");
        cy.get("#latestNotesList").should("contain.text", text.userPrivate);
        cy.get("#latestNotesList").should("contain.text", text.publicPerson);
        cy.get("#latestNotesList").should("contain.text", text.publicFamily);
        cy.get("#latestNotesList").should("not.contain.text", text.adminPrivate);
        cy.get("#latestNotesList .list-group-item")
            .contains(text.userPrivate)
            .closest(".list-group-item")
            .find(".fa-lock")
            .should("exist");
    });

    it("shows only public notes to notes-only staff", () => {
        cy.setupPersonaSession("notes-only-staff");
        cy.visit("/v2/dashboard");
        cy.get("#latestNotesList").should("contain.text", text.publicPerson);
        cy.get("#latestNotesList").should("not.contain.text", text.adminPrivate);
        cy.get("#latestNotesList").should("not.contain.text", text.userPrivate);
    });

    it("is not shown to staff without notes access", () => {
        cy.setupPersonaSession("office-staff");
        cy.visit("/v2/dashboard");
        cy.get("#birthdayCard").should("exist");
        cy.get("#latestNotesCard").should("not.exist");
        cy.contains(text.publicPerson).should("not.exist");
    });
});
