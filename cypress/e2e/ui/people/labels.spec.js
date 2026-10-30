/// <reference types="cypress" />

import { pdfDrawing, pdfText } from "../../../support/pdf-text";

// Seed: July birthdays give the Birthdays report rows. Lena Black (100) and Samantha Black (102)
// have photos. The church is "Main St. Cathedral".
const STOCKS = ["5160", "5161", "5162", "5163", "5164", "8600", "L7163", "Tractor"];
const mailing = (labelType, extra = "") =>
    `/people/labels?report=birthdays&month=7&labeltype=${labelType}&labelfont=Helvetica&labelfontsize=default&startrow=1&startcol=1&filetype=PDF${extra}`;
const familyLabels = (kind) =>
    `/people/labels/${kind}?labeltype=5160&labelfont=Helvetica&labelfontsize=default&recipientnamingmethod=familyname`;
const NAME_TAGS = "/people/labels/name-tags?labeltype=74536&labelfont=Times&labelfontsize=36";

const MM = 72 / 25.4;

const fetchPdf = (url) =>
    cy.request({ url, encoding: "binary" }).then((response) => {
        expect(response.headers["content-type"]).to.include("application/pdf");
        expect(response.body.slice(0, 5)).to.eq("%PDF-");
        return response.body;
    });

const cart = (method, body = {}) =>
    cy.request({ method, url: "/api/cart/", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });

describe("Label sheets", () => {
    it("prints mailing labels on every stock for a staff user", () => {
        cy.setupPersonaSession("zero-permission-staff");
        STOCKS.forEach((labelType) => fetchPdf(mailing(labelType)));
    });

    it("prints the first label at the start row and column", () => {
        cy.setupAdminSession();
        const drawing = (url) => fetchPdf(url).then((body) => pdfDrawing(body));
        drawing(mailing("5160")).then((plain) => {
            const topLeft = plain.find((item) => item.text);
            drawing(mailing("5160", "&startrow=3&startcol=2")).then((started) => {
                const placed = started.find((item) => item.text === topLeft.text);
                expect(placed, `${topLeft.text} on the started sheet`).to.not.equal(undefined);
                expect(placed.x - topLeft.x, "one 5160 column across").to.be.closeTo((66.675 + 3.175) * MM, 0.5);
                expect(topLeft.y - placed.y, "two 5160 rows down").to.be.closeTo(2 * 25.4 * MM, 0.5);
            });
        });
    });

    it("rejects an unknown label stock", () => {
        cy.setupAdminSession();
        cy.request({ url: mailing("nope"), failOnStatusCode: false }).its("status").should("eq", 400);
    });

    it("prints newsletter and confirm labels", () => {
        cy.setupAdminSession();
        fetchPdf(familyLabels("newsletter"));
        fetchPdf(familyLabels("confirm"));
    });

    it("prints a name badge with the church name and no person ID", () => {
        cy.setupAdminSession();
        cart("DELETE");
        cart("POST", { Persons: [100, 102] });
        fetchPdf(NAME_TAGS)
            .then((body) => pdfText(body))
            .then((lines) => {
                expect(lines).to.include.members(["MAIN ST. CATHEDRAL", "Lena", "Samantha", "Black"]);
                expect(lines).to.not.include("100");
                expect(lines).to.not.include("102");
            });
        cart("DELETE");
    });

    it("sends a self-service user back to the portal", () => {
        cy.setupPersonaSession("portal-only");
        [mailing("5160"), NAME_TAGS, familyLabels("newsletter"), familyLabels("confirm")].forEach((url) => {
            cy.request({ url, followRedirect: false, failOnStatusCode: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.headers.location).to.include("/portal");
            });
        });
    });
});
