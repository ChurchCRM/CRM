/// <reference types="cypress" />

context("Standard Sunday School", () => {
    it("View Sunday School dashboard", () => {
        cy.loginStandard("sundayschool/SundaySchoolDashboard.php");
        cy.contains("Sunday School Dashboard");
        cy.contains("Sunday School Classes");
        cy.contains("Students not in a Sunday School Class");
    });
});
