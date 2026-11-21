/// <reference types="cypress" />

describe("Cart - pending self-registrations", () => {
    const stamp = Date.now();
    const lastName = `Cartpending${stamp}`;
    const email = `cart-pending-${stamp}@example.com`;
    let personId;

    const labelQuery =
        "labeltype=5160&labelfont=Helvetica&labelfontsize=10&groupbymode=indiv&onlyfull=0&startrow=1&startcol=1&filetype=CSV";

    const cart = (method, body = {}) =>
        cy.request({ method, url: "/api/cart/", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });

    before(() => {
        cy.request({
            method: "POST",
            url: "/api/public/register/person",
            body: { firstName: "Cypress", lastName, email, gender: 1 },
        }).then((response) => {
            personId = response.body.Id;
            expect(personId).to.be.greaterThan(0);
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cart("DELETE");
        cart("POST", { Persons: [personId] });
    });

    after(() => {
        cy.setupAdminSession();
        cart("DELETE");
        if (personId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, [200, 404]);
        }
    });

    it("leaves a person awaiting review out of the cart email list", () => {
        cy.request("/api/cart/emails").then(({ body }) => {
            expect(body.recipients.map((recipient) => recipient.personId)).not.to.include(personId);
            expect(body.emails).not.to.include(email);
        });
    });

    it("leaves a person awaiting review off cart mailing labels", () => {
        cy.request(`/people/labels?${labelQuery}`).then((response) => {
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).not.to.include(lastName);
        });
    });

    it("includes the person once they are approved", () => {
        cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/approve-review`, null, 200);
        cy.setupAdminSession();
        cart("DELETE");
        cart("POST", { Persons: [personId] });

        cy.request("/api/cart/emails").then(({ body }) => {
            expect(body.recipients.map((recipient) => recipient.personId)).to.include(personId);
            expect(body.emails).to.include(email);
        });
        cy.request(`/people/labels?${labelQuery}`).then((response) => {
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).to.include(lastName);
        });
    });
});
