/// <reference types="cypress" />

describe("Deposit API", () => {
  it("creates a new deposit via API", () => {
  const depositType = "BankDraft";
  const depositComment = "API Test Deposit";
  const depositDate = new Date().toISOString().slice(0, 10); // 'YYYY-MM-DD'

    cy.makePrivateAdminAPICall(
      "POST",
      "/api/deposits",
      { depositType, depositComment, depositDate },
      200
    ).then((resp) => {
      expect(resp.body).to.have.property("Type", depositType);
      expect(resp.body).to.have.property("Comment", depositComment);
      expect(resp.body).to.have.property("Date", depositDate);
      expect(resp.body).to.have.property("Id");
    });
  });
});
