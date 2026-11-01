/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

describe("Directory report - household names", () => {
    const lewisFamilyId = 3;

    const cartRequest = (method, body) =>
        cy.request({
            method,
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body),
        });

    beforeEach(() => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
        cartRequest("POST", { Family: lewisFamilyId });
    });

    after(() => {
        cy.setupAdminSession();
        cartRequest("DELETE", {});
    });

    it("names the head once when the head and spouse roles are the same", () => {
        cy.request({
            method: "POST",
            url: "/people/reports/directory",
            form: true,
            encoding: "binary",
            body: {
                cartdir: "M",
                "sDirRoleHead[]": "1",
                "sDirRoleSpouse[]": "1",
                bDirAddress: "1",
                sDirLayout: "pages",
                NumCols: "1",
                PageSize: "letter",
                FSize: "10",
            },
        })
            .then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                return pdfText(response.body);
            })
            .then((lines) => {
                expect(lines, "the household heading").to.include("Lewis, Nathan");
                expect(lines.filter((line) => line.includes("Nathan and Nathan"))).to.be.empty;
            });
    });
});
