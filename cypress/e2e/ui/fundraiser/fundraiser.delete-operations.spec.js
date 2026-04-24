/// <reference types="cypress" />

// GHSA-3xq9-c86x-cwpp — confirmation + CSRF guard on fundraiser delete pages.
describe("Fundraiser Delete Operations", () => {
    beforeEach(() => cy.setupStandardSession());

    describe("DonatedItemDelete.php", () => {
        it("renders confirmation form with a CSRF token", () => {
            cy.visit("/DonatedItemDelete.php?DonatedItemID=1&linkBack=FindFundRaiser.php");
            cy.contains("Confirm Delete");
            cy.get('input[name="csrf_token"]').should("have.attr", "value").and("match", /^[a-f0-9]{64}$/);
            cy.get('input[name="Delete"]').should("exist");
            cy.get('input[name="Cancel"]').should("exist");
        });

        it("does not delete on GET", () => {
            // A GET must render the confirmation form (HTTP 200) and must never
            // follow the delete-then-redirect path — which would surface as a
            // 302 because the POST Delete handler ends with RedirectUtils::redirect.
            cy.request({
                method: "GET",
                url: "/DonatedItemDelete.php?DonatedItemID=1&linkBack=FindFundRaiser.php",
                followRedirect: false,
            }).then((resp) => {
                expect(resp.status, "GET must render form, not 302-redirect").to.eq(200);
                expect(resp.body).to.match(/name="DonatedItemID"\s+value="1"/);
                expect(resp.body).to.match(/name="csrf_token"\s+value="[a-f0-9]{64}"/);
            });
        });

        it("rejects POST without a valid CSRF token", () => {
            cy.request({
                method: "POST",
                url: "DonatedItemDelete.php",
                form: true,
                body: {
                    DonatedItemID: "1",
                    Delete: "Delete",
                    csrf_token: "bogus",
                },
                failOnStatusCode: false,
            }).its("status").should("eq", 403);
        });
    });

    describe("PaddleNumDelete.php", () => {
        it("renders confirmation form with a CSRF token", () => {
            cy.visit("/PaddleNumDelete.php?PaddleNumID=1&linkBack=FindFundRaiser.php");
            cy.contains("Confirm Delete");
            cy.get('input[name="csrf_token"]').should("have.attr", "value").and("match", /^[a-f0-9]{64}$/);
            cy.get('input[name="Delete"]').should("exist");
            cy.get('input[name="Cancel"]').should("exist");
        });

        it("does not delete on GET", () => {
            // A GET must render the confirmation form (HTTP 200) and must never
            // follow the delete-then-redirect path — which would surface as a
            // 302 because the POST Delete handler ends with RedirectUtils::redirect.
            cy.request({
                method: "GET",
                url: "/PaddleNumDelete.php?PaddleNumID=1&linkBack=FindFundRaiser.php",
                followRedirect: false,
            }).then((resp) => {
                expect(resp.status, "GET must render form, not 302-redirect").to.eq(200);
                expect(resp.body).to.match(/name="PaddleNumID"\s+value="1"/);
                expect(resp.body).to.match(/name="csrf_token"\s+value="[a-f0-9]{64}"/);
            });
        });

        it("rejects POST without a valid CSRF token", () => {
            cy.request({
                method: "POST",
                url: "PaddleNumDelete.php",
                form: true,
                body: {
                    PaddleNumID: "1",
                    Delete: "Delete",
                    csrf_token: "bogus",
                },
                failOnStatusCode: false,
            }).its("status").should("eq", 403);
        });
    });
});
