/// <reference types="cypress" />

const WRITES = [
    ["POST", "/api/person/2/note", { text: "<p>x</p>" }],
    ["POST", "/api/family/2/note", { text: "<p>x</p>" }],
    ["POST", "/api/person/2/addToCart", {}],
    ["POST", "/api/person/2/photo", {}],
    ["POST", "/api/family/1/photo", {}],
    ["POST", "/api/people/properties/person/2/1", {}],
];

describe("zero-permission-staff: reads people, writes nothing", () => {
    it("reads a person record", () => {
        cy.personaAPICall("zero-permission-staff", "GET", "/api/person/1", null, 200);
    });

    WRITES.forEach(([method, url, body]) => {
        it(`${method} ${url} is refused`, () => {
            cy.personaAPICall("zero-permission-staff", method, url, body, 403).then((response) => {
                expect(response.body.success).to.eq(false);
            });
        });
    });
});
