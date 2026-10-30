/// <reference types="cypress" />

const SUBJECT = 'subject:"Campbell: Please verify"';
const search = () => `${Cypress.mailpitUrl()}/api/v1/search?query=${encodeURIComponent(SUBJECT)}`;
const PHONE_WIDTH = 375;

describe("Email link", () => {
    it("wraps the copy-and-paste link so the email stays within a phone screen", () => {
        cy.request({ method: "DELETE", url: search() });
        cy.makePrivateAdminAPICall("POST", "/api/family/1/verify", null, 200);
        cy.request(`${search()}&limit=1`)
            .then((found) => cy.request(`${Cypress.mailpitUrl()}/api/v1/message/${found.body.messages[0].ID}`))
            .then((message) => {
                cy.viewport(PHONE_WIDTH, 800);
                cy.visit("/session/begin");
                cy.document().then((doc) => {
                    doc.open();
                    doc.write(message.body.HTML);
                    doc.close();
                });
            });
        cy.contains("a", "/external/verify/").should("be.visible");
        cy.document().its("documentElement.scrollWidth").should("be.lte", PHONE_WIDTH);
    });
});
