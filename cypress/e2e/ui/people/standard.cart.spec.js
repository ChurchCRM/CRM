/// <reference types="cypress" />

context("Standard Cart", () => {
    beforeEach(() => {});

    it("Cart Add and Remove Person", () => {
        cy.loginStandard("v2/cart");
        cy.contains("You have no items in your cart");
        cy.visit("PersonView.php?PersonID=1");
        cy.get("#AddPersonToCart").click();
        cy.intercept("GET", "/api/cart/").as("getNewCart");
        cy.wait("@getNewCart");
        cy.visit("v2/cart");
        cy.contains("Cart Functions");
        cy.contains("Church Admin");
        cy.get("#emptyCart").click();
        cy.contains("You have no items in your cart");
    });

    it("Cart Add and Remove Family", () => {
        cy.loginStandard("v2/cart");
        cy.contains("You have no items in your cart");
        cy.visit("v2/family/6");
        cy.get("#AddFamilyToCart").click();
        cy.visit("v2/cart");
        cy.contains("Kenzi Dixon");
        cy.contains("Cart Functions");
        cy.get("#emptyCart").click();
        cy.contains("You have no items in your cart");
    });
});
