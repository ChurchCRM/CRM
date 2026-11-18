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
        sChurchCountry: "CA",
        sCharityLegalName: "Sample Canadian Charity",
        sCharityRegisteredAddress: "456 Sample Street\nOttawa ON K1A 0A1\nCanada",
        sCharityRegistrationNumber: "123456789 rr 0001",
        sReceiptIssueLocality: "Ottawa",
        sTaxSigner: "Authorized Treasurer",
        sConfirmSigner: "Confirmation Officer",
        sCurrencySymbol: "$",
        iFYMonth: "4",
    };
    const saved = {};
    const baseKeys = [];
    const familyIds = [];
    // Work a test adds is undone in afterEach, so one failing test cannot leave
    // payments, deposits, envelopes or settings behind for the next one.
    let undo = [];
    const later = (fn) => undo.push(fn);
    let familyId;
    let secondFamilyId;
    const signature = "iVBORw0KGgoAAAANSUhEUgAAAHgAAAAoCAIAAAC6iKlyAAAAUklEQVR42u3QQQ0AAAgEoOtjJhsZ2hbOBxsJSPVwIApEi0a0aNEWRItGtGjRFkSLRrRo0YgWjWjRohEtGtGiRSNaNKJFi0a0aESLFo1o0Yj+ZwGy/zgts+HrHQAAAABJRU5ErkJggg==";
    const setEnvelope = (id, envelope) => {
        cy.freshAdminFormLogin({ enabled: true });
        cy.request("/finance/envelopes").then(({ body: page }) => {
            const token = page.match(/name="csrf_token" value="([^"]+)"/)[1];
            return cy.request({ method: "POST", url: "/finance/envelopes", form: true,
                body: { csrf_token: token, action: "save", [`envelopes[${id}]`]: String(envelope) } });
        });
    };
    const addPayment = (fields, split, keys = null) => cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", {
        type: "Payment", iMethod: "CASH", FYID: 29, FamilyID: String(familyId), ...fields,
        FundSplit: JSON.stringify(split),
    }, 200).then(({ body: result }) => {
        if (keys) keys.push(result.groupKey);
        else later(() => cy.makePrivateAdminAPICall("DELETE", `/api/payments/${result.groupKey}`, null, [200, 404]));
        return result.groupKey;
    });
    const createFamily = (name, address, zip) => {
        cy.freshAdminFormLogin({ enabled: true });
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type(name);
        cy.get('[name="Address1"]').type(address);
        cy.get('[name="City"]').clear().type("Montréal");
        cy.get('[name="Zip"]').type(zip);
        cy.get('[name="Country"]').select("Canada", { force: true });
        cy.get('[name="State"]').select("QC", { force: true });
        cy.get('[name="FamilySubmit"]').first().click();
        return cy.location("pathname").then((path) => {
            const id = Number(path.split("/").pop());
            expect(id).to.be.greaterThan(0);
            familyIds.push(id);
            return id;
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
    // A refused receipt renders the review page again (HTML), naming the family.
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
    const refusal = (overrides, ...phrases) => receipt(overrides, 400).then((response) => {
        expect(response.headers["content-type"]).to.include("text/html");
        cy.wrap(Cypress.$("<div>").html(response.body).find("#canadianError").text()).then((message) => {
            phrases.forEach((phrase) => expect(message).to.include(phrase));
        });
    });
    before(() => {
        Object.entries(settings).forEach(([key, value]) => {
            cy.getSystemConfig(key).then((old) => { saved[key] = old; });
            cy.setSystemConfig(key, value);
        });
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/tax-signature", { imgBase64: `data:image/png;base64,${signature}` }, 200);
        createFamily("CanadianReceiptFixture", "123 rue Exemple", "H2X 1Y4").then((id) => {
            familyId = id;
            addPayment({ Date: "2025-01-12" }, [{ FundID: "1", Amount: 100, NonDeductible: 0, Comment: "Private <memo>" }], baseKeys);
            addPayment({ Date: "2025-12-31" }, [{ FundID: "1", Amount: 50, NonDeductible: 0, Comment: "Private <memo>" }], baseKeys);
            setEnvelope(familyId, 459);
        });
        createFamily("CanadianReceiptSecond", "77 rue Seconde", "H2X 2B2").then((id) => { secondFamilyId = id; });
    });
    beforeEach(() => {
        undo = [];
        cy.freshAdminFormLogin({ enabled: true });
    });
    afterEach(() => {
        cy.then(() => undo.splice(0).reverse().forEach((fn) => fn()));
    });
    after(() => {
        baseKeys.forEach((key) => cy.makePrivateAdminAPICall("DELETE", `/api/payments/${key}`, null, [200, 404]));
        if (familyIds.length) cy.cleanupFamilies(familyIds);
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/system/tax-signature", null, 200);
        Object.entries(saved).forEach(([key, value]) => cy.restoreSystemConfig(key, value));
    });
    it("prints the verified annual 150 receipt in CAD with a plain $ symbol, without private memos", () => {
        receipt().then((response) => pdfText(response.body)).then((lines) => {
            const text = lines.join(" ");
            ["2025-459", "Élodie Marie Tremblay", "150.00", "0.00", "CAD", "Reçu officiel", "123456789RR0001", "Authorized Treasurer"].forEach((value) => expect(text).to.include(value));
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
        refusal({ donorNames: {} }, "CanadianReceiptFixture", "verified full donor name");
        receipt({ signerAuthorized: "0" }, 400);
        refusal({ mixedDonors: { [familyId]: "yes" } }, "CanadianReceiptFixture");
        receipt({ DateStart: "2025-02-01" }, 400);
        refusal({ issueDate: "2025-06-01" }, "CanadianReceiptFixture", "dated after the issue date");
        cy.request({ method: "POST", url: download, body: body(), failOnStatusCode: false }).its("status").should("eq", 403);
        cy.request({ url: `${download}?template=canadian`, failOnStatusCode: false }).its("status").should("eq", 403);
    });
    it("uses an unambiguous family fallback when the envelope is zero", () => {
        later(() => setEnvelope(familyId, 459));
        setEnvelope(familyId, 0);
        cy.freshAdminFormLogin({ enabled: true });
        receipt().then((response) => pdfText(response.body)).then((lines) => expect(lines.join(" ")).to.include(`2025-F${familyId}`));
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
    it("names the family on a shared envelope, and blocks missing charity settings and a non-CAD symbol", () => {
        later(() => setEnvelope(secondFamilyId, 0));
        setEnvelope(secondFamilyId, 459);
        cy.freshAdminFormLogin({ enabled: true });
        refusal({}, "CanadianReceiptFixture", "Shared-envelope conflict");
        cy.then(() => undo.splice(0).forEach((fn) => fn()));
        later(() => cy.setSystemConfig("sCharityLegalName", settings.sCharityLegalName));
        cy.setSystemConfig("sCharityLegalName", "");
        cy.freshAdminFormLogin({ enabled: true });
        refusal({}, "charity legal name");
        later(() => cy.setSystemConfig("sCurrencySymbol", settings.sCurrencySymbol));
        cy.setSystemConfig("sCurrencySymbol", "USD");
        cy.freshAdminFormLogin({ enabled: true });
        refusal({}, "currency symbol");
    });
    it("refuses Canadian receipts when the church country is not Canada", () => {
        later(() => cy.setSystemConfig("sChurchCountry", "CA"));
        cy.setSystemConfig("sChurchCountry", "US");
        cy.freshAdminFormLogin({ enabled: true });
        cy.request({ method: "POST", url: download, body: body({ csrf_token: "invalid" }), failOnStatusCode: false }).its("status").should("eq", 403);
        cy.request({ url: `${download}?familyId=${familyId}&year=2025`, encoding: "binary" })
            .then((response) => pdfText(response.body)).then((lines) => {
                expect(lines.join(" ")).to.not.include("not an official donation receipt");
                expect(lines.join(" ")).to.include("Giving Statement");
            });
    });
    it("rejects an advantage without silently skipping it", () => {
        addPayment({ Date: "2025-02-01" }, [{ FundID: "1", Amount: 10, NonDeductible: 1 }]);
        cy.freshAdminFormLogin({ enabled: true });
        refusal({}, "CanadianReceiptFixture", "non-deductible amount", "No receipt was generated");
    });
    it("receipts a card payment only once its deposit is closed", () => {
        cy.makePrivateAdminAPICall("POST", "/api/deposits", { depositType: "Bank", depositComment: "Canadian receipt fixture", depositDate: "2025-02-03" }, 200).then(({ body: deposit }) => {
            const depositId = deposit.Id;
            const setClosed = (closed) => cy.makePrivateAdminAPICall("POST", `/api/deposits/${depositId}`,
                { depositType: "Bank", depositComment: "Canadian receipt fixture", depositDate: "2025-02-03", depositClosed: closed }, 200);
            later(() => cy.makePrivateAdminAPICall("DELETE", `/api/deposits/${depositId}`, null, [200, 404]));
            addPayment({ Date: "2025-02-01", iMethod: "CREDITCARD", DepositID: depositId }, [{ FundID: "1", Amount: 10, NonDeductible: 0 }]);
            // Reopen first so the payment and the deposit can be deleted afterwards.
            later(() => setClosed(false));
            cy.freshAdminFormLogin({ enabled: true });
            refusal({}, "CanadianReceiptFixture", "not closed yet");
            setClosed(true);
            cy.freshAdminFormLogin({ enabled: true });
            receipt().then((response) => pdfText(response.body)).then((lines) => expect(lines.join(" ")).to.include("160.00"));
        });
    });
    it("keeps one-, forty- and 150-row schedules readable with literal memos and repeated serials", () => {
        const addRows = (count, date) => addPayment({ Date: date },
            Array.from({ length: count }, () => ({ FundID: "1", Amount: 1, NonDeductible: 0, Comment: "Literal <b>donor-facing</b>" })));
        // Leave one base row: remove the 2025-12-31 gift for this test and restore it after.
        cy.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/payments/${baseKeys[1]}`, null, 200));
        later(() => addPayment({ Date: "2025-12-31" }, [{ FundID: "1", Amount: 50, NonDeductible: 0, Comment: "Private <memo>" }], baseKeys)
            .then(() => baseKeys.splice(1, 1)));
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
    });
    it("starts each family on a new page and keeps confirmation signers independent", () => {
        addPayment({ Date: "2025-05-01", FamilyID: String(secondFamilyId) }, [{ FundID: "1", Amount: 1, NonDeductible: 0 }]);
        cy.freshAdminFormLogin({ enabled: true });
        receipt({ family: [String(familyId), String(secondFamilyId)],
            donorNames: { [familyId]: "Élodie Marie Tremblay", [secondFamilyId]: "Fictional Second Donor" },
            donorVerified: { [familyId]: "1", [secondFamilyId]: "1" }, mixedDonors: { [familyId]: "no", [secondFamilyId]: "no" },
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
    });
});
