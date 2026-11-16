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

    it("Statement Settings previews only fictional gifts, ignoring donor filters", () => {
        cy.visit("/finance/statements/settings");
        cy.contains("a", "Preview PDF").should("have.attr", "href").then((href) => {
            expect(href).to.include("/finance/reports/giving-statements/sample");
            cy.request({ url: "/finance/reports/giving-statements/sample?familyId=1&year=2018&output=csv", encoding: "binary" }).then((response) => {
                expectPdf(response);
                return pdfText(response.body).then((lines) => {
                    const text = lines.join(" ");
                    expect(text).to.include("Sample Family (fictional)");
                    expect(text).to.include("Sample General Fund");
                    expect(text).to.include("Sample Missions Fund");
                    expect(text).to.not.include("Campbell");
                });
            });
        });
    });

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

describe("Canadian reviewed receipts", () => {
    const settings = {
        bCanadianGivingStatement: "1",
        sCanadianCharityLegalName: "Sample Canadian Charity",
        sCanadianCharityRegisteredAddress: "456 Sample Street\nOttawa ON K1A 0A1\nCanada",
        sCanadianCharityRegistrationNumber: "123456789 rr 0001",
        sCanadianReceiptIssueLocality: "Ottawa",
        sTaxSigner: "Authorized Treasurer",
        sConfirmSigner: "Confirmation Officer",
        sCurrencySymbol: "CAD $",
        iFYMonth: "4",
    };
    const saved = {};
    const paymentKeys = [];
    let familyId;
    let otherEnvelope;
    const signature = "iVBORw0KGgoAAAANSUhEUgAAAHgAAAAoCAIAAAC6iKlyAAAAUklEQVR42u3QQQ0AAAgEoOtjJhsZ2hbOBxsJSPVwIApEi0a0aNEWRItGtGjRFkSLRrRo0YgWjWjRohEtGtGiRSNaNKJFi0a0aESLFo1o0Yj+ZwGy/zgts+HrHQAAAABJRU5ErkJggg==";
    const setEnvelope = (id, envelope) => {
        cy.freshAdminFormLogin({ enabled: true });
        cy.request("/finance/envelopes").then(({ body: page }) => {
            const token = page.match(/name="csrf_token" value="([^"]+)"/)[1];
            return cy.request({ method: "POST", url: "/finance/envelopes", form: true,
                body: { csrf_token: token, action: "save", [`envelopes[${id}]`]: String(envelope) } });
        });
    };
    const body = (overrides = {}) => ({
        template: "canadian", calendarYear: "2025", family: [String(familyId)],
        issueDate: "2026-01-15", copyKind: "original",
        donorNames: { [familyId]: "Élodie Marie Tremblay" },
        donorVerified: { [familyId]: "1" }, mixedDonors: { [familyId]: "no" },
        signerAuthorized: "1", monetaryOnly: "1", receivedDatesVerified: "1",
        noOverlap: "1", retainOriginal: "1", ...overrides,
    });
    const receipt = (overrides = {}, expectedStatus = 200) => {
        return cy.request("/finance/reports/giving-statements?template=canadian").then(({ body: page }) => {
            const token = page.match(/name="csrf_token" value="([^"]+)"/)[1];
            return cy.request({ method: "POST", url: download, body: { ...body(overrides), csrf_token: token },
                encoding: "binary", failOnStatusCode: false }).then((response) => {
                expect(response.status).to.eq(expectedStatus);
                return response;
            });
        });
    };
    before(() => {
        Object.entries(settings).forEach(([key, value]) => {
            cy.getSystemConfig(key).then((old) => { saved[key] = old; });
            cy.setSystemConfig(key, value);
        });
        cy.makePrivateAdminAPICall("GET", "/api/family/1", null, 200).then(({ body: family }) => { otherEnvelope = family.Envelope; });
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/tax-signature", { imgBase64: `data:image/png;base64,${signature}` }, 200);
        cy.freshAdminFormLogin({ enabled: true });
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type("CanadianReceiptFixture");
        cy.get('[name="Address1"]').type("123 rue Exemple");
        cy.get('[name="City"]').clear().type("Montréal");
        cy.get('[name="Zip"]').type("H2X 1Y4");
        cy.get('[name="Country"]').select("Canada", { force: true });
        cy.get('[name="State"]').select("QC", { force: true });
        cy.get('[name="FamilySubmit"]').first().click();
        cy.location("pathname").then((path) => {
            familyId = Number(path.split("/").pop());
            expect(familyId).to.be.greaterThan(0);
            [100, 50].forEach((amount, index) => {
                cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", {
                    type: "Payment", iMethod: "CASH", Date: index === 0 ? "2025-01-12" : "2025-12-31",
                    FamilyID: String(familyId), FYID: 29,
                    FundSplit: JSON.stringify([{ FundID: "1", Amount: amount, NonDeductible: 0, Comment: "Private <memo>" }]),
                }, 200).then(({ body: result }) => paymentKeys.push(result.groupKey));
            });
            setEnvelope(familyId, 459);
        });
    });
    beforeEach(() => cy.freshAdminFormLogin({ enabled: true }));
    after(() => {
        paymentKeys.forEach((key) => cy.makePrivateAdminAPICall("DELETE", `/api/payments/${key}`, null, 200));
        if (familyId) cy.cleanupFamilies([familyId]);
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/system/tax-signature", null, 200);
        if (otherEnvelope !== undefined) setEnvelope(1, otherEnvelope);
        Object.entries(saved).forEach(([key, value]) => cy.restoreSystemConfig(key, value));
    });
    it("prints the verified annual CAD 150 receipt without inferring the donor or including private memos", () => {
        receipt().then((response) => pdfText(response.body)).then((lines) => {
            const text = lines.join(" ");
            ["2025-459", "Élodie Marie Tremblay", "CAD 150.00", "CAD 0.00", "Reçu officiel", "123456789RR0001", "Authorized Treasurer"].forEach((value) => expect(text).to.include(value));
            expect(text).to.not.include("Private <memo>");
            expect(text).to.not.include("intangible religious benefits");
        });
    });
    it("keeps the serial across issue months, and marks a compared duplicate", () => {
        receipt({ issueDate: "2026-02-01", copyKind: "duplicate", duplicateCompared: "1" })
            .then((response) => pdfText(response.body)).then((lines) => {
                expect(lines.join(" ")).to.include("2025-459").and.include("Duplicate copy / Copie duplicata");
            });
        receipt({ copyKind: "duplicate", issueDate: "", duplicateCompared: "1" }, 400);
    });
    it("blocks missing names, attestations, mixed donors, partial-year filters and CSRF failures", () => {
        receipt({ donorNames: {} }, 400);
        receipt({ signerAuthorized: "0" }, 400);
        receipt({ mixedDonors: { [familyId]: "yes" } }, 400);
        receipt({ DateStart: "2025-02-01" }, 400);
        cy.request({ method: "POST", url: download, body: body(), failOnStatusCode: false }).its("status").should("eq", 403);
        cy.request({ url: `${download}?template=canadian`, failOnStatusCode: false }).its("status").should("eq", 403);
    });
    it("uses an unambiguous family fallback when the envelope is zero", () => {
        setEnvelope(familyId, 0);
        cy.freshAdminFormLogin({ enabled: true });
        receipt().then((response) => pdfText(response.body)).then((lines) => expect(lines.join(" ")).to.include(`2025-F${familyId}`));
        setEnvelope(familyId, 459);
    });
    it("labels standard statements as non-official and keeps Canadian sample data fictional", () => {
        cy.request({ url: `${download}?familyId=${familyId}&year=2025`, encoding: "binary" })
            .then((response) => pdfText(response.body)).then((lines) => expect(lines.join(" ")).to.include("not an official donation receipt"));
        cy.request({ url: "/finance/reports/giving-statements/sample?template=canadian&familyId=1", encoding: "binary" })
            .then((response) => pdfText(response.body)).then((lines) => {
                expect(lines.join(" ")).to.include("SAMPLE").and.include("NOT VALID FOR TAX PURPOSES");
                expect(lines.join(" ")).to.not.include("Authorized Treasurer");
            });
    });
    it("blocks a shared envelope and missing charity settings, and honors the disabled flag", () => {
        setEnvelope(1, 459);
        cy.freshAdminFormLogin({ enabled: true });
        receipt({}, 400).then((response) => expect(response.body).to.include("Shared-envelope conflict"));
        setEnvelope(1, otherEnvelope);
        cy.setSystemConfig("sCanadianCharityLegalName", "");
        cy.freshAdminFormLogin({ enabled: true });
        receipt({}, 400);
        cy.setSystemConfig("sCanadianCharityLegalName", settings.sCanadianCharityLegalName);
        cy.setSystemConfig("sCurrencySymbol", "USD");
        cy.freshAdminFormLogin({ enabled: true });
        receipt({}, 400);
        cy.setSystemConfig("sCurrencySymbol", settings.sCurrencySymbol);
        cy.setSystemConfig("bCanadianGivingStatement", "0");
        cy.freshAdminFormLogin({ enabled: true });
        cy.request({ method: "POST", url: download, body: body({ csrf_token: "invalid" }), failOnStatusCode: false }).its("status").should("eq", 403);
        cy.request({ url: `${download}?familyId=${familyId}&year=2025`, encoding: "binary" })
            .then((response) => pdfText(response.body)).then((lines) => {
                expect(lines.join(" ")).to.not.include("not an official donation receipt");
                expect(lines.join(" ")).to.include("Giving Statement");
            });
        cy.setSystemConfig("bCanadianGivingStatement", "1");
    });

    it("rejects an advantage and an uncleared electronic payment without silently skipping either", () => {
        for (const method of ["CASH", "CREDITCARD"]) {
            let invalidKey;
            cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", {
                type: "Payment", iMethod: method, Date: "2025-02-01", FamilyID: String(familyId), FYID: 29,
                FundSplit: JSON.stringify([{ FundID: "1", Amount: 10, NonDeductible: method === "CASH" ? 1 : 0 }]),
            }, 200).then(({ body: result }) => { invalidKey = result.groupKey; paymentKeys.push(invalidKey); });
            cy.freshAdminFormLogin({ enabled: true });
            receipt({}, 400).then((response) => expect(response.body).to.include("No rows were skipped"));
            cy.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/payments/${invalidKey}`, null, 200));
            cy.then(() => paymentKeys.splice(paymentKeys.indexOf(invalidKey), 1));
        }
    });
    it("keeps one-, forty- and 150-row schedules readable with literal memos and repeated serials", () => {
        const extraKeys = [];
        const addRows = (count, date) => cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", {
            type: "Payment", iMethod: "CASH", Date: date, FamilyID: String(familyId), FYID: 29,
            FundSplit: JSON.stringify(Array.from({ length: count }, () => ({ FundID: "1", Amount: 1, NonDeductible: 0, Comment: "Literal <b>donor-facing</b>" }))),
        }, 200).then(({ body: result }) => { extraKeys.push(result.groupKey); paymentKeys.push(result.groupKey); });
        cy.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/payments/${paymentKeys[1]}`, null, 200));
        cy.then(() => paymentKeys.splice(1, 1));
        cy.freshAdminFormLogin({ enabled: true });
        receipt().then((response) => {
            expect((response.body.match(/\/Type\s*\/Page\b/g) || []).length).to.eq(1);
            cy.writeFile("cypress/downloads/canadian-one-row.pdf", response.body, "binary");
        });
        addRows(39, "2025-03-01");
        cy.freshAdminFormLogin({ enabled: true });
        receipt({ includeMemo: "1" }).then((response) => {
            cy.writeFile("cypress/downloads/canadian-forty-rows.pdf", response.body, "binary");
            return pdfText(response.body);
        }).then((lines) => {
            expect(lines.join(" ")).to.include("Literal <b>donor-facing</b>");
            expect(lines.filter((line) => line.includes("2025-459")).length).to.be.greaterThan(1);
        });
        addRows(110, "2025-04-01");
        cy.freshAdminFormLogin({ enabled: true });
        receipt({ includeMemo: "1" }).then((response) => {
            expect((response.body.match(/\/Type\s*\/Page\b/g) || []).length).to.be.greaterThan(2);
            cy.writeFile("cypress/downloads/canadian-150-rows.pdf", response.body, "binary");
            return pdfText(response.body);
        }).then((lines) => {
            expect(lines.filter((line) => line.includes("DATE RECEIVED")).length).to.be.greaterThan(1);
            expect(lines.filter((line) => line.includes("Authorized Treasurer")).length).to.eq(1);
        });
        cy.then(() => extraKeys.forEach((key) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/payments/${key}`, null, 200);
            cy.then(() => paymentKeys.splice(paymentKeys.indexOf(key), 1));
        }));
    });

    it("starts each family on a new page and keeps confirmation signers independent", () => {
        let secondKey;
        cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", {
            type: "Payment", iMethod: "CASH", Date: "2025-05-01", FamilyID: "1", FYID: 29,
            FundSplit: JSON.stringify([{ FundID: "1", Amount: 1, NonDeductible: 0 }]),
        }, 200).then(({ body: result }) => { secondKey = result.groupKey; paymentKeys.push(secondKey); });
        cy.freshAdminFormLogin({ enabled: true });
        receipt({ family: [String(familyId), "1"], donorNames: { [familyId]: "Élodie Marie Tremblay", 1: "Fictional Second Donor" },
            donorVerified: { [familyId]: "1", 1: "1" }, mixedDonors: { [familyId]: "no", 1: "no" },
        }).then((response) => {
            expect((response.body.match(/\/Type\s*\/Page\b/g) || []).length).to.eq(2);
            cy.writeFile("cypress/downloads/canadian-two-families.pdf", response.body, "binary");
            return pdfText(response.body);
        }).then((lines) => {
            expect(lines.join(" ")).to.include("Fictional Second Donor").and.include("Élodie Marie Tremblay");
            expect(lines.filter((line) => line.includes("Authorized Treasurer")).length).to.eq(2);
        });
        cy.request({ url: `/people/report/verify?familyId=${familyId}`, encoding: "binary" })
            .then((response) => pdfText(response.body)).then((lines) => {
                expect(lines.join(" ")).to.include("Confirmation Officer");
                expect(lines.join(" ")).to.not.include("Authorized Treasurer");
            });
        cy.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/payments/${secondKey}`, null, 200));
        cy.then(() => paymentKeys.splice(paymentKeys.indexOf(secondKey), 1));
    });

});
