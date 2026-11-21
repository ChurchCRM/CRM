/// <reference types="cypress" />

import { restoreUnassignedPeople, snapshotUnassignedPeople } from "../../support/family-conversion-fixtures";
import { createPersonInEditor } from "../../support/person-editor";

// "Aaa" sorts these people ahead of every seed person without a family, so Convert Next takes them first.
const LAST_NAME = "Aaafamconv";

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

describe("Administrator converts people without a family into families", () => {
    const created = { people: [], families: [] };
    let seed = null;

    after(() => {
        if (seed) {
            seed.families.forEach((id) => {
                cy.makePrivateAdminAPICall("DELETE", `/api/family/${id}`, null, 200);
            });
            restoreUnassignedPeople(seed.snapshot, seed.personId);
        }
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

    it("converts the next person from People Settings", () => {
        createLoner("Devon").then((personId) => {
            created.people.push(personId);
            cy.visit("/admin/people");
            cy.contains("a", "Convert Individuals to Families").click();
            cy.location("pathname").should("equal", "/admin/people/convert-to-families");

            cy.get("#familyConversionNext").should("have.text", `Devon ${LAST_NAME}`);
            cy.get("#familyConversionCount")
                .invoke("text")
                .then((before) => {
                    const count = Number(before.match(/\d+/)[0]);
                    cy.get("#familyConversionConvertNext").click();

                    cy.location("search").should("match", /^\?family=\d+$/);
                    cy.get("#familyConversionDone").should("contain", LAST_NAME);
                    cy.get("#familyConversionCount").should("contain", String(count - 1));
                    cy.get("#familyConversionNext").should("not.have.text", `Devon ${LAST_NAME}`);
                });
            cy.request(`/api/person/${personId}`)
                .its("body.FamId")
                .then((famId) => {
                    expect(Number(famId)).to.be.greaterThan(0);
                    created.families.push(Number(famId));
                });
        });
    });

    it("converts everyone without a family at once", () => {
        createLoner("Emery").then((personId) => {
            created.people.push(personId);
            snapshotUnassignedPeople().then((snapshot) => {
                cy.intercept("POST", "**/admin/api/people/convert-unassigned-to-families").as("convertAll");
                cy.visit("/admin/people/convert-to-families");
                cy.get("#familyConversionConvertAll").click();
                cy.get(".bootbox .btn-warning").click();

                cy.wait("@convertAll").then(({ response }) => {
                    const { families } = response.body;
                    created.families.push(...families.filter((family) => family.name === LAST_NAME).map((family) => family.id));
                    seed = {
                        snapshot,
                        personId,
                        families: families.filter((family) => family.name !== LAST_NAME).map((family) => family.id),
                    };
                    expect(families).to.have.length(snapshot.people.length);
                });
                cy.location("search").should("equal", `?converted=${snapshot.people.length}`);
                cy.get("#familyConversionDone").should("contain", String(snapshot.people.length));
                cy.get("#familyConversionNothing").should("be.visible");
            });
        });
    });
});
