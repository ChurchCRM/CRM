/// <reference types="cypress" />

const options = "labeltype=5160&labelfont=Helvetica&labelfontsize=default&recipientnamingmethod=familyname";
const NAME_TAGS = "/people/labels/name-tags?labeltype=74536&labelfont=Times&labelfontsize=36";

const expectPdf = (url) =>
    cy.request({ url, encoding: "binary" }).then((response) => {
        expect(response.headers["content-type"]).to.include("application/pdf");
        expect(response.body.slice(0, 5)).to.eq("%PDF-");
    });

const cartRequest = (method, body) =>
    cy.request({ method, url: "/api/cart/", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });

describe("Name tags, newsletter labels and confirm labels", () => {
    it("a staff user gets the three label PDFs", () => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
        cartRequest("POST", { Family: 1 });
        expectPdf(NAME_TAGS);
        cartRequest("DELETE", {});
        expectPdf(`/people/labels/newsletter?${options}`);
        expectPdf(`/people/labels/confirm?${options}`);
    });

    it("the cart offers the name tags", () => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
        cartRequest("POST", { Family: 1 });
        cy.visit("/people/cart");
        cy.get('a[href*="/people/labels/name-tags"]').should("be.visible");
        cartRequest("DELETE", {});
    });

    it("a self-service user is sent to access denied", () => {
        cy.setupPersonaSession("portal-only");
        [NAME_TAGS, `/people/labels/newsletter?${options}`, `/people/labels/confirm?${options}`].forEach((url) => {
            cy.request({ url, followRedirect: false, failOnStatusCode: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.headers.location).to.include("v2/access-denied");
            });
        });
    });
});
