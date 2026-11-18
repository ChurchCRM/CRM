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

    it("is listed with the giving reports and opens on the current fiscal year", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit("/finance/reports");
        cy.get('#financeReports a[href$="/finance/reports/zero-givers"]').first().click();
        cy.location("pathname").should("match", /\/finance\/reports\/zero-givers$/);
        cy.get("#zeroGiversForm").should("exist");
        cy.get("#zeroGiversMessage").should("not.exist");
        cy.get("#letterheadaddress").should("be.checked");

        const today = new Date().toISOString().slice(0, 10);
        cy.get("#DateStart").invoke("val").then((start) => {
            cy.get("#DateEnd").invoke("val").then((end) => {
                expect(start).to.match(/^\d{4}-\d{2}-01$/);
                expect(start <= today && today <= end, `${start} <= ${today} <= ${end}`).to.eq(true);
            });
        });
    });

    it("is no longer offered on the legacy report picker", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit("/FinancialReports.php?ReportType=Zero%20Givers");
        cy.get("#FinancialReportTypes").should("exist");
        cy.get('#FinancialReportTypes option[value="Zero Givers"]').should("not.exist");
    });

    it("downloads the CSV for the dates a treasurer picks on the form", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit("/finance/reports/zero-givers");
        cy.intercept("GET", "**/finance/reports/zero-givers/download*").as("download");
        cy.get("#DateStart").clear().type("1990-01-01");
        cy.get("#DateEnd").clear().type("1990-12-31");
        cy.get("#letterheadnone").check();
        cy.get("#downloadZeroGiversCsv").click();
        cy.wait("@download").then(({ request, response }) => {
            const query = new URL(request.url).searchParams;
            expect(query.get("DateStart")).to.eq("1990-01-01");
            expect(query.get("DateEnd")).to.eq("1990-12-31");
            expect(query.get("letterhead")).to.eq("none");
            expect(query.get("output")).to.eq("csv");
            expect(response.statusCode).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).to.include(activeName);
        });
    });

    describe("when every member family gave in the period", () => {
        const day = "1995-06-15";
        const groupKeys = [];

        before(() => {
            cy.setupAdminSession();
            cy.makePrivateAdminAPICall("GET", "/api/fiscalyear", null, 200).its("body.fyId").then((fyId) => {
                cy.request({
                    url: "/finance/reports/zero-givers/download",
                    qs: { DateStart: day, DateEnd: day, output: "csv" },
                }).then(({ body }) => {
                    const ids = body.trim().split(/\r?\n/).slice(1).map((row) => row.split(",")[0]);
                    expect(ids).not.to.be.empty;
                    ids.forEach((id) =>
                        cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", {
                            type: "Payment",
                            iMethod: "CASH",
                            Date: day,
                            FamilyID: id,
                            FYID: fyId,
                            FundSplit: JSON.stringify([{ FundID: "1", Amount: 1, NonDeductible: 0, Comment: "zero givers empty period" }]),
                        }, 200).then(({ body: created }) => groupKeys.push(created.payment.GroupKey)),
                    );
                });
            });
        });

        after(() => {
            cy.setupAdminSession();
            cy.wrap(groupKeys).each((groupKey) =>
                cy.makePrivateAdminAPICall("DELETE", `/api/payments/${encodeURIComponent(groupKey)}`, null, [200, 404]),
            );
        });

        it("stays on the form with a no-rows message and the chosen filters", () => {
            cy.setupPersonaSession("finance-only");
            cy.visit("/finance/reports/zero-givers");
            cy.get("#DateStart").clear().type(day);
            cy.get("#DateEnd").clear().type(day);
            cy.get("#letterheadnone").check();
            cy.get("#downloadZeroGivers").click();

            cy.location("pathname").should("match", /\/finance\/reports\/zero-givers\/download$/);
            cy.get("#zeroGiversMessage").should("be.visible").and("contain", "No Data Found");
            cy.get("#DateStart").should("have.value", day);
            cy.get("#DateEnd").should("have.value", day);
            cy.get("#letterheadnone").should("be.checked");
        });

        it("answers a posted request with the same page instead of a redirect", () => {
            cy.setupPersonaSession("finance-only");
            cy.request({
                method: "POST",
                url: "finance/reports/zero-givers/download",
                form: true,
                body: { DateStart: day, DateEnd: day, output: "csv" },
                followRedirect: false,
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/html");
                expect(response.body).to.include('id="zeroGiversMessage"');
            });
        });
    });

    it("office staff are sent to access denied", () => {
        cy.setupPersonaSession("office-staff");
        zeroGivers("pdf").then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
        cy.request({ url: "/finance/reports/zero-givers", failOnStatusCode: false, followRedirect: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });
});
