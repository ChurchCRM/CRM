/// <reference types="cypress" />

// GHSA-5xh2-3xjw-wr7j. Seeded data: person 1 is an administrator and person 99
// has a login; person 10 has no login. Person 3 (tony.wade) is a non-admin
// with Edit Records.
describe("Email of a person who has a login", () => {
    const stamp = Cypress._.random(0, 1e6);
    const changed = `locked.${stamp}@example.com`;
    let original = "";

    const saveEmail = (id, email) => {
        cy.visit(`/PersonEditor.php?PersonID=${id}`);
        cy.get("#Email").clear();
        if (email) {
            cy.get("#Email").type(email);
        }
        cy.get('button[name="PersonSubmit"]').click();
    };

    describe("standard user", () => {
        beforeEach(() => cy.setupStandardSession());

        it("sees the email read-only on an account holder", () => {
            cy.visit("/PersonEditor.php?PersonID=1");
            cy.get("#Email").should("have.attr", "readonly");
        });

        it("keeps the stored email when a posted change is forced through", () => {
            cy.visit("/PersonEditor.php?PersonID=99");
            cy.get("#Email")
                .invoke("val")
                .then((before) => {
                    cy.get("#Email").invoke("removeAttr", "readonly").clear().type(changed);
                    cy.get('button[name="PersonSubmit"]').click();
                    cy.visit("/PersonEditor.php?PersonID=99");
                    cy.get("#Email").should("have.value", before);
                });
        });

        it("can still edit the email of a person without a login", () => {
            cy.visit("/PersonEditor.php?PersonID=10");
            cy.get("#Email").should("not.have.attr", "readonly");
        });
    });

    describe("administrator", () => {
        beforeEach(() => cy.setupAdminSession());

        it("changes the email and the person's timeline records it", () => {
            cy.visit("/PersonEditor.php?PersonID=99");
            cy.get("#Email").should("not.have.attr", "readonly");
            cy.get("#Email")
                .invoke("val")
                .then((val) => {
                    original = val;
                });

            saveEmail(99, changed);

            cy.visit("/PersonEditor.php?PersonID=99");
            cy.get("#Email").should("have.value", changed);
            cy.visit("/people/view/99");
            cy.contains("Email changed by an administrator").should("exist");
            cy.contains(changed).should("exist");
        });

        after(() => {
            cy.setupAdminSession();
            saveEmail(99, original);
        });
    });
});
