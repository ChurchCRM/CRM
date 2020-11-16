/// <reference types="cypress" />

context('Standard Cart', () => {

    it('Cart Add and Remove', () => {
        cy.loginStandard("v2/cart");
        cy.contains('You have no items in your cart');
        cy.visit("PersonView.php?PersonID=1")
        cy.get("#AddPersonToCart").click();
        cy.visit("v2/cart");
        cy.contains('Cart Functions');
        cy.contains('Church Admin');
        cy.get("#emptyCart").click();
        cy.contains('You have no items in your cart');
    });

});
