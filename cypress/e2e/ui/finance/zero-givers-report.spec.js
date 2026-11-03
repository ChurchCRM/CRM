/// <reference types="cypress" />

import { pdfText } from "../../../support/pdf-text";

const zeroGivers = (output) =>
    cy.request({
        method: "POST",
        url: "finance/reports/zero-givers/download",
        form: true,
        body: { DateStart: "1990-01-01", DateEnd: "1990-12-31", letterhead: "address", output },
        encoding: "binary",
        failOnStatusCode: false,
        followRedirect: false,
    });

describe("Zero Givers report", () => {
    const familyIds = [];
    const activeName = `ZeroActive${Date.now()}`;
    const inactiveName = `ZeroInactive${Date.now()}`;

    before(() => {
        cy.setupAdminSession();
        [activeName, inactiveName].forEach((name) => {
            cy.visit("/FamilyEditor.php");
            cy.get("#FamilyName").type(name);
            ["Ann", "Ben"].forEach((firstName, index) => {
                cy.get(`input[name="FirstName${index + 1}"]`).type(firstName);
                cy.get(`select[name="Classification${index + 1}"]`).select("1", { force: true });
            });
            cy.get('button[name="FamilySubmit"]').click();
            cy.location("pathname").should("match", /\/people\/family\/\d+$/).then((pathname) => {
                familyIds.push(Number(pathname.split("/").pop()));
            });
        });
        cy.then(() => cy.request("POST", `/api/family/${familyIds[1]}/activate/false`));
    });

    after(() => {
        cy.cleanupFamilies(familyIds);
    });

    it("finance staff get the PDF letters", () => {
        cy.setupPersonaSession("finance-only");
        zeroGivers("pdf").then((response) => {
            expect(response.headers["content-type"]).to.include("application/pdf");
            expect(response.body.slice(0, 5)).to.eq("%PDF-");
        });
    });

    it("finance staff get the CSV", () => {
        cy.setupPersonaSession("finance-only");
        zeroGivers("csv").then((response) => {
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).to.include("fam_ID");
        });
    });

    it("includes each active family once and omits inactive families from CSV and PDF", () => {
        cy.setupPersonaSession("finance-only");
        zeroGivers("csv").then((response) => {
            expect(response.status).to.eq(200);
            const ids = response.body.trim().split(/\r?\n/).slice(1).map((row) => row.split(",")[0]);
            expect(ids.filter((id) => id === String(familyIds[0]))).to.have.length(1);
            expect(ids).not.to.include(String(familyIds[1]));
        });
        zeroGivers("pdf").then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("application/pdf");
            return pdfText(response.body).then((lines) => {
                const text = lines.join(" ");
                expect(text).to.include(activeName);
                expect(text).not.to.include(inactiveName);
            });
        });
    });

    it("repeats the letterhead on every family's letter", () => {
        cy.setupAdminSession();
        cy.request("/admin/api/system/config/sChurchName").its("body.value").then((churchName) => {
            zeroGivers("csv").then((csv) => {
                const letters = csv.body.trim().split(/\r?\n/).length - 1;
                expect(letters).to.be.greaterThan(1);
                zeroGivers("pdf").then((pdf) =>
                    pdfText(pdf.body).then((lines) => {
                        expect(lines.filter((line) => line.includes(churchName)).length).to.be.at.least(letters);
                    }),
                );
            });
        });
    });

    it("office staff are sent to access denied", () => {
        cy.setupPersonaSession("office-staff");
        zeroGivers("pdf").then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });
});
