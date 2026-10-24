/// <reference types="cypress" />

// #10725: delete and edit act on every row with a GroupKey, so a pledge and a
// payment must never share one.
describe("Pledge and payment GroupKeys (#10725)", () => {
    const familyId = 9;
    const fundId = "1";
    const createdKeys = [];
    const createdDeposits = [];
    let fyId;

    const fundSplit = (amount) => JSON.stringify([{ FundID: fundId, Amount: amount, NonDeductible: 0, Comment: "" }]);

    const pledgeBody = (date, amount) => ({
        type: "Pledge",
        iMethod: "CASH",
        FamilyID: familyId,
        Date: date,
        FYID: fyId,
        schedule: "Once",
        FundSplit: fundSplit(amount),
    });

    const paymentBody = (date, amount, depositId) => ({
        type: "Payment",
        iMethod: "CASH",
        FamilyID: familyId,
        Date: date,
        FYID: fyId,
        DepositID: depositId,
        FundSplit: fundSplit(amount),
    });

    const groupUrl = (key) => `/api/payments/pledges/${encodeURIComponent(key)}`;
    const writeUrl = (key) => `/api/payments/${encodeURIComponent(key)}`;

    const record = (body) =>
        cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", body, 200).then((resp) => {
            createdKeys.push(resp.body.groupKey);
            return resp.body.groupKey;
        });

    const openDeposit = () =>
        cy
            .makePrivateAdminAPICall(
                "POST",
                "/api/deposits",
                { depositType: "Bank", depositComment: "GroupKey #10725", depositDate: "2024-05-01" },
                200,
            )
            .then((resp) => {
                createdDeposits.push(resp.body.Id);
                return resp.body.Id;
            });

    const closeDeposit = (depositId) =>
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/deposits/${depositId}`,
            { depositType: "Bank", depositComment: "GroupKey #10725", depositDate: "2024-05-01", depositClosed: true },
            200,
        );

    // The edit page loads the group, then saves with the type it shows.
    const editAsTheEditorDoes = (key, date, amount, depositId, expectedStatus = 200) =>
        cy.makePrivateAdminAPICall("GET", groupUrl(key), null, 200).then(({ body }) => {
            const save = body.pledgeOrPayment === "Payment" ? paymentBody(date, amount, depositId) : pledgeBody(date, amount);
            return cy.makePrivateAdminAPICall("PUT", writeUrl(key), { ...save, type: body.pledgeOrPayment }, expectedStatus);
        });

    before(() => {
        cy.makePrivateAdminAPICall("GET", "/api/fiscalyear?date=2024-05-01", null, 200).then((resp) => {
            fyId = resp.body.fyId;
        });
    });

    after(() => {
        createdKeys.forEach((key) => cy.makePrivateAdminAPICall("DELETE", writeUrl(key), null, [200, 404]));
        createdDeposits.forEach((id) => cy.makePrivateAdminAPICall("DELETE", `/api/deposits/${id}`, null, [200, 404]));
    });

    it("gives a cash payment its own key, so deleting it leaves the pledge for the same family, fund and date", () => {
        const date = "2024-05-06";
        openDeposit().then((depositId) => {
            record(pledgeBody(date, 500)).then((pledgeKey) => {
                record(paymentBody(date, 25, depositId)).then((paymentKey) => {
                    expect(paymentKey).to.not.equal(pledgeKey);

                    cy.makePrivateAdminAPICall("GET", groupUrl(paymentKey), null, 200).then(({ body }) => {
                        expect(body.pledgeOrPayment).to.equal("Payment");
                        expect(body.depositId).to.equal(depositId);
                        expect(body.total).to.be.closeTo(25, 0.01);
                    });

                    cy.makePrivateAdminAPICall("DELETE", writeUrl(paymentKey), null, 200);
                    cy.makePrivateAdminAPICall("GET", groupUrl(paymentKey), null, 404);
                    cy.makePrivateAdminAPICall("GET", groupUrl(pledgeKey), null, 200).then(({ body }) => {
                        expect(body.pledgeOrPayment).to.equal("Pledge");
                        expect(body.total).to.be.closeTo(500, 0.01);
                    });
                });
            });
        });
    });

    it("gives two pledges for the same family, fund and date different keys", () => {
        const date = "2024-05-07";
        record(pledgeBody(date, 100)).then((firstKey) => {
            record(pledgeBody(date, 200)).then((secondKey) => {
                expect(secondKey).to.not.equal(firstKey);

                cy.makePrivateAdminAPICall("DELETE", writeUrl(secondKey), null, 200);
                cy.makePrivateAdminAPICall("GET", groupUrl(firstKey), null, 200).then(({ body }) => {
                    expect(body.funds).to.have.length(1);
                    expect(body.total).to.be.closeTo(100, 0.01);
                });
            });
        });
    });

    it("keeps an edited payment a payment in its deposit and leaves the pledge alone", () => {
        const date = "2024-05-08";
        openDeposit().then((depositId) => {
            record(pledgeBody(date, 1200)).then((pledgeKey) => {
                record(paymentBody(date, 100, depositId)).then((paymentKey) => {
                    editAsTheEditorDoes(paymentKey, date, 150, depositId);

                    cy.makePrivateAdminAPICall("GET", groupUrl(paymentKey), null, 200).then(({ body }) => {
                        expect(body.pledgeOrPayment).to.equal("Payment");
                        expect(body.depositId).to.equal(depositId);
                        expect(body.total).to.be.closeTo(150, 0.01);
                    });
                    cy.makePrivateAdminAPICall("GET", groupUrl(pledgeKey), null, 200).then(({ body }) => {
                        expect(body.pledgeOrPayment).to.equal("Pledge");
                        expect(body.total).to.be.closeTo(1200, 0.01);
                    });
                });
            });
        });
    });

    it("leaves a payment in a closed deposit when the pledge is edited, and refuses to edit the payment", () => {
        const date = "2024-05-09";
        openDeposit().then((depositId) => {
            record(pledgeBody(date, 600)).then((pledgeKey) => {
                record(paymentBody(date, 40, depositId)).then((paymentKey) => {
                    closeDeposit(depositId);

                    editAsTheEditorDoes(pledgeKey, date, 700, depositId);
                    editAsTheEditorDoes(paymentKey, date, 45, depositId, 409);

                    cy.makePrivateAdminAPICall("GET", groupUrl(paymentKey), null, 200).then(({ body }) => {
                        expect(body.pledgeOrPayment).to.equal("Payment");
                        expect(body.depositId).to.equal(depositId);
                        expect(body.total).to.be.closeTo(40, 0.01);
                    });
                    cy.makePrivateAdminAPICall("GET", groupUrl(pledgeKey), null, 200).then(({ body }) => {
                        expect(body.total).to.be.closeTo(700, 0.01);
                    });
                });
            });
        });
    });
});
