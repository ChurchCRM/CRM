/// <reference types="cypress" />

describe("Finance summaries name the person who gave", () => {
    const MEMBER_OF_FAMILY_1 = 2;
    const PERSON_WITHOUT_FAMILY = 3;
    const FUND_ID = 1;
    const createdKeys = [];
    let depositId;

    const record = (type, personId, amount) =>
        cy
            .makePrivateAdminAPICall(
                "POST",
                "/api/payments/pledges",
                {
                    type,
                    iMethod: "CASH",
                    Date: "2025-10-25",
                    FYID: 29,
                    schedule: "Once",
                    PersonID: personId,
                    DepositID: type === "Payment" ? depositId : null,
                    tScanString: "",
                    FundSplit: JSON.stringify([{ FundID: String(FUND_ID), Amount: amount, NonDeductible: 0, Comment: "" }]),
                },
                200,
            )
            .then((resp) => createdKeys.push(resp.body.groupKey));

    before(() => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/deposits",
            { depositType: "Bank", depositComment: "Donor person spec", depositDate: "2025-10-25" },
            200,
        ).then((resp) => {
            depositId = resp.body.Id;
            record("Pledge", MEMBER_OF_FAMILY_1, 300);
            record("Payment", MEMBER_OF_FAMILY_1, 120);
            record("Payment", PERSON_WITHOUT_FAMILY, 75);
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        createdKeys.forEach((key) =>
            cy.makePrivateAdminAPICall("DELETE", `/api/payments/${encodeURIComponent(key)}`, null, [200, 404]),
        );
        cy.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/deposits/${depositId}`, null, [200, 404]));
    });

    it("lists each person as their own contributor to the fund", () => {
        cy.visit(`/finance/fund/${FUND_ID}/contributors?fyid=0`);
        cy.get("#fundContributors tbody tr")
            .contains("tr", "Mathew Campbell")
            .within(() => {
                cy.get('a[href$="/people/view/2"]').should("exist");
                cy.get('a[href$="/people/family/1"]').should("exist");
                cy.contains("120");
                cy.contains("300");
            });
        cy.get("#fundContributors tbody tr").contains("tr", "Tony Campbell").should("contain", "75");
    });

    it("shows the person on the Pledge Dashboard", () => {
        cy.visit("/finance/pledge/dashboard?fyid=0");
        cy.get("#familyPledges tbody").should("contain", "Mathew Campbell").and("contain", "Tony Campbell");
    });

    it("adds the person to the deposit CSV export", () => {
        cy.makePrivateAdminAPICall("GET", `/api/deposits/${depositId}/csv`, null, 200).then((resp) => {
            const [header, ...rows] = resp.body.trim().split(/\r?\n/);
            expect(header).to.include("Person");
            expect(rows.some((row) => row.includes("Tony Campbell"))).to.equal(true);
        });
    });
});
