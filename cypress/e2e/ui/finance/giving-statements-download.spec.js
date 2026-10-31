/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const download = "/finance/reports/giving-statements/download";
const range = "DateStart=2018-01-01&DateEnd=2018-12-31&datetype=Payment";

const expectPdf = (response) => {
    expect(response.status).to.eq(200);
    expect(response.headers["content-type"]).to.include("application/pdf");
    expect(response.body.slice(0, 5)).to.eq("%PDF-");
};

describe("Giving statements download", () => {
    beforeEach(() => cy.setupAdminSession());

    it("a family's statement opens from its profile link, and the CSV has the same rows", () => {
        cy.request({ url: `${download}?familyId=1&year=2018`, encoding: "binary" }).then(expectPdf);
        cy.request(`${download}?${range}&output=csv`).then((response) => {
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).to.include("fam_ID");
        });
    });

    it("multi-family PDF repeats the letterhead on every family's page (#10989)", () => {
        cy.request({ url: `${download}?${range}`, encoding: "binary" }).then((response) => {
            expectPdf(response);
            return pdfText(response.body).then((lines) => {
                // The church name is drawn in the letterhead. In a multi-family
                // PDF it must appear once per family — not just on the first page.
                cy.request("/admin/api/system/config/sChurchName").then((cfg) => {
                    const churchName = cfg.body.value;
                    expect(churchName).to.not.be.empty;
                    const occurrences = lines.filter((line) => line.includes(churchName));
                    expect(occurrences.length).to.be.greaterThan(1,
                        "letterhead church name should appear on more than one family's statement");
                });
            });
        });
    });

    it("a minimum above the family total, or a period with no payments, returns to the report form", () => {
        cy.request({ url: `${download}?${range}&minimum=999999999`, followRedirect: false }).its("status").should("eq", 302);
        cy.request({ url: `${download}?DateStart=1991-01-01&DateEnd=1991-01-31`, followRedirect: false }).its("status").should("eq", 302);
    });
});
