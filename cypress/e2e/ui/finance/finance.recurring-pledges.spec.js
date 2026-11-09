/// <reference types="cypress" />
import { pdfText } from "../../../support/pdf-text";

const created = [];
const pledge = (overrides = {}) => ({
    type: "Pledge", iMethod: "CASH", FamilyID: "1", Date: "2026-01-01", FYID: 30,
    DepositID: null, schedule: "Monthly", amountBasis: "installment",
    firstInstallment: "2026-01-01", lastInstallment: "2026-12-31",
    FundSplit: JSON.stringify([{ FundID: "1", Amount: 100, NonDeductible: 0, Comment: "Recurring pledge regression" }]),
    ...overrides,
});
const create = (payload) => cy.request("POST", "/api/payments/pledges", payload).then(({ body }) => {
    created.push(body.groupKey);
    return body.groupKey;
});

describe("Recurring pledge commitment", () => {
    beforeEach(() => cy.setupAdminSession());
    afterEach(() => {
        created.splice(0).forEach((key) => cy.request("DELETE", `/api/payments/${encodeURIComponent(key)}`));
    });

    it("previews, saves and edits monthly installment amounts without multiplying twice", () => {
        cy.visit("/finance/pledge/new?type=Pledge&familyId=1");
        cy.get("#Date").clear().type("2026-01-01");
        cy.get("#FYID").select("30");
        cy.get("#AmountBasis").select("installment");
        cy.get("#Schedule").select("Monthly");
        cy.get("#FirstInstallment").type("2026-01-01");
        cy.get("#LastInstallment").type("2026-12-31");
        cy.get("#fundRows .fund-select").select("1");
        cy.get("#fundRows .fund-amount").clear().type("100");
        cy.get("#commitmentTotal").should("contain", "1,200.00");
        cy.get("#installmentPreview").should("contain", "12");
        cy.intercept("POST", "**/api/payments/pledges").as("save");
        cy.get("#savePledgeBtn").click();
        cy.wait("@save").then(({ response }) => {
            expect(response.statusCode).to.eq(200);
            const key = response.body.groupKey;
            created.push(key);
            cy.location("pathname").should("include", "/finance/pledge/");
            cy.visit(`/finance/pledge/${encodeURIComponent(key)}/edit`);
            cy.get("#AmountBasis").should("have.value", "installment");
            cy.get("#fundRows .fund-amount").should("have.value", "100.00");
            cy.get("#commitmentTotal").should("contain", "1,200.00");
            cy.intercept("PUT", "**/api/payments/*").as("update");
            cy.get("#savePledgeBtn").click();
            cy.wait("@update").its("response.statusCode").should("eq", 200);
            cy.request(`/api/payments/pledges/${encodeURIComponent(key)}`).its("body.total").should("eq", 1200);
        });
    });

    it("uses the full commitment in family summaries and reminder letters", () => {
        create(pledge());
        create(pledge({ type: "Payment", amountBasis: "total", FundSplit: JSON.stringify([{ FundID: "1", Amount: 200, NonDeductible: 0 }]) }));
        cy.visit("/finance/pledge/dashboard?fyid=30");
        cy.get("body").should("contain", "1,200.00").and("contain", "200.00");
        ["family-pledge-summary", "pledge-reminders"].forEach((report) => {
            cy.request({ url: `/finance/reports/${report}/download?FYID=30&family[]=1&funds[]=1`, encoding: "binary" }).then((response) => {
                expect(response.headers["content-type"]).to.include("application/pdf");
                return pdfText(response.body).then((lines) => {
                    const text = lines.join(" ");
                    expect(text).to.include("1,200.00");
                    expect(text).to.include("200.00");
                    expect(text).to.include("1,000.00");
                });
            });
        });
    });

    it("preserves legacy Monthly totals and allows explicit conversion", () => {
        const legacy = pledge();
        delete legacy.amountBasis;
        create(legacy).then((key) => {
            cy.request(`/api/payments/pledges/${encodeURIComponent(key)}`).then(({ body }) => {
                expect(body.total).to.eq(100);
                expect(body.amountBasis).to.eq("total");
            });
            cy.request("PUT", `/api/payments/${encodeURIComponent(key)}`, legacy).its("status").should("eq", 200);
            cy.request("PUT", `/api/payments/${encodeURIComponent(key)}`, pledge()).its("status").should("eq", 200);
            cy.request(`/api/payments/pledges/${encodeURIComponent(key)}`).its("body.total").should("eq", 1200);
            cy.request({ method: "PUT", url: `/api/payments/${encodeURIComponent(key)}`, body: legacy, failOnStatusCode: false }).its("status").should("eq", 409);
            cy.request({ method: "PUT", url: `/api/payments/${encodeURIComponent(key)}`, body: pledge({ lastInstallment: "2027-01-01" }), failOnStatusCode: false }).its("status").should("eq", 400);
            cy.request(`/api/payments/pledges/${encodeURIComponent(key)}`).its("body.total").should("eq", 1200);
        });
    });

    it("validates all funds before saving and rejects installment payments", () => {
        [
            pledge({ lastInstallment: "2027-01-01" }),
            pledge({ firstInstallment: "2026-02-30" }),
            pledge({ type: "Payment" }),
            pledge({ FundSplit: JSON.stringify([{ FundID: "1", Amount: 100 }, { FundID: "2", Amount: 100000 }]) }),
        ].forEach((body) => {
            cy.request({ method: "POST", url: "/api/payments/pledges", body, failOnStatusCode: false }).its("status").should("eq", 400);
        });
    });

    it("keeps preview and pledge creation behind finance permission", () => {
        cy.setupPersonaSession("office-staff");
        cy.request({ url: "/api/payments/pledge-installments?schedule=Monthly&first=2026-01-01&last=2026-12-31&fyId=30", failOnStatusCode: false }).its("status").should("eq", 403);
        cy.request({ method: "POST", url: "/api/payments/pledges", body: pledge(), failOnStatusCode: false }).its("status").should("eq", 403);
    });
});
