/// <reference types="cypress" />

import { createPersonInEditor } from "../../support/person-editor";

const LAST_NAME = "Famconvui";

const createLoner = (firstName) =>
    createPersonInEditor({
        FirstName: firstName,
        LastName: LAST_NAME,
        Gender: 1,
        Classification: 1,
        Family: 0,
        Address1: "7 Elm Court",
        City: "Lakeside",
        HomePhone: "(555) 010-4455",
    });

describe("Administrator converts a person without a family into a family", () => {
    const created = { people: [], families: [] };

    after(() => {
        created.families.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${id}?deleteMembers=true`, null, [200, 404]);
        });
        created.people.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${id}`, null, [200, 404]);
        });
    });

    it("gives a person a family of their own from the person page", () => {
        createLoner("Casey").then((personId) => {
            created.people.push(personId);
            cy.visit(`/people/view/${personId}`);
            cy.get("#person-actions-dropdown").click();
            cy.get("#convertToFamily").click();
            cy.get(".bootbox .modal-title").should("have.text", "Convert to Family");
            cy.get(".bootbox .btn-primary").click();

            cy.location("pathname")
                .should("match", /\/people\/family\/\d+$/)
                .then((pathname) => {
                    const familyId = Number(pathname.split("/").pop());
                    created.families.push(familyId);
                    cy.contains("7 Elm Court");
                    cy.request(`/api/person/${personId}`).its("body.FamId").should("equal", familyId);
                });

            cy.visit(`/people/view/${personId}`);
            cy.get("#person-actions-dropdown").click();
            cy.get("#convertToFamily").should("not.exist");
        });
    });
});
