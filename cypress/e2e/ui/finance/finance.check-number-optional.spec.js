/// <reference types="cypress" />

/**
 * Treasurer at a church that does not record check numbers (#9772).
 * With "Require check number on check payments" off, a check saves with a
 * blank or 0 check number as often as needed; a real number still has to be
 * unique per family.
 */
describe("Treasurer records checks without check numbers", () => {
    const familyId = 1;
    let depositId;
    let savedRequireCheckNumber;

    before(() => {
        cy.getSystemConfig("bRequireCheckNumber").then((value) => {
            savedRequireCheckNumber = value;
        });
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/deposits",
            { depositType: "Bank", depositComment: `Checks without numbers ${Date.now()}` },
            200,
        ).then((response) => {
            depositId = response.body.Id;
        });
    });

    after(() => {
        cy.makePrivateAdminAPICall("DELETE", `/api/deposits/${depositId}`, null, 200);
        cy.restoreSystemConfig("bRequireCheckNumber", savedRequireCheckNumber);
    });

    beforeEach(() => cy.setupAdminSession());

    const saveCheck = (checkNumber, amount) => {
        cy.intercept("POST", "**/api/payments/pledges").as("savePayment");
        cy.visit(`/finance/pledge/new?type=Payment&depositId=${depositId}&familyId=${familyId}`);
        cy.get("#Method").select("CHECK");
        cy.get("#CheckNo").clear();
        if (checkNumber !== "") {
            cy.get("#CheckNo").type(checkNumber);
        }
        cy.get(".fund-select").first().select(1);
        cy.get(".fund-amount").first().clear().type(amount);
        cy.get("#savePledgeBtn").click();
        return cy.wait("@savePayment").its("response");
    };

    it("turns off the requirement in Financial Settings, then saves blank and 0 check numbers twice for one family", () => {
        cy.intercept("POST", "**/admin/api/system/config/bRequireCheckNumber").as("saveRequireCheckNumber");
        cy.visit("/finance/");
        cy.contains("Financial Settings").click();
        cy.get("#financialSettings .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
        cy.get("#financialSettings input[name='bRequireCheckNumber'][value='0']").check({ force: true });
        cy.wait("@saveRequireCheckNumber").its("response.statusCode").should("eq", 200);
        cy.getSystemConfig("bRequireCheckNumber").should("eq", "0");

        saveCheck("", "11").its("statusCode").should("eq", 200);
        saveCheck("", "12").its("statusCode").should("eq", 200);
        saveCheck("0", "13").its("statusCode").should("eq", 200);
        saveCheck("0", "14").its("statusCode").should("eq", 200);

        cy.request(`/api/deposits/${depositId}/payments`).then((response) => {
            const amounts = response.body.map((payment) => Number(payment.sumAmount));
            expect(amounts).to.have.members([11, 12, 13, 14]);
        });
    });

    it("still rejects a real check number the family already used", () => {
        cy.setSystemConfig("bRequireCheckNumber", "0");
        const checkNumber = String(Math.floor(Math.random() * 90000000) + 10000000);

        saveCheck(checkNumber, "21").its("statusCode").should("eq", 200);
        saveCheck(checkNumber, "22").then((response) => {
            expect(response.statusCode).to.eq(400);
            expect(response.body.message).to.contain(`Check number '${checkNumber}' for selected family already exists.`);
        });
        cy.location("pathname").should("match", /\/finance\/pledge\/new$/);
    });
});
