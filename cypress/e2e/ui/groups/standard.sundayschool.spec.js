/// <reference types="cypress" />

describe("Standard Sunday School", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("View Sunday School dashboard", () => {
        cy.visit("sundayschool/SundaySchoolDashboard.php");
        cy.contains("Sunday School Dashboard");
        cy.contains("Sunday School Classes");
        cy.contains("Students not in a Sunday School Class");
    });
});
