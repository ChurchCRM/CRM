/// <reference types="cypress" />

require('cy-verify-downloads').addCustomCommand();

context('Financial Reports', () => {

    it('Giving Report', () => {
        cy.loginAdmin("FinancialReports.php");
        cy.contains('Financial Reports');
        cy.get('#FinancialReportTypes').select('Giving Report');
        cy.get('#FinancialReports').submit();
        cy.contains('Financial Reports: Giving Report');
        cy.get('#createReport').click();
        cy.contains('No records were returned from the previous report.');
    });

    it('Pledge Summary', () => {
        cy.loginAdmin("FinancialReports.php");
        cy.contains('Financial Reports');
        cy.get('#FinancialReportTypes').select('Pledge Summary');
        cy.get('#FinancialReports').submit();
        cy.contains('Financial Reports: Pledge Summary');
        //cy.get('#createReport').click();
        //cy.verifyDownload('.pdf', { contains: true });
    });

    it('Pledge Family Summary', () => {
        cy.loginAdmin("FinancialReports.php");
        cy.contains('Financial Reports');
        cy.get('#FinancialReportTypes').select('Pledge Family Summary');
        cy.get('#FinancialReports').submit();
        cy.contains('Financial Reports: Pledge Family Summary');
        cy.get('#createReport').click();
    });

    it('Pledge Reminders', () => {
        cy.loginAdmin("FinancialReports.php");
        cy.contains('Financial Reports');
        cy.get('#FinancialReportTypes').select('Pledge Reminders');
        cy.get('#FinancialReports').submit();
        cy.contains('Financial Reports: Pledge Reminders');
        cy.get('#createReport').click();
    });

    it('Voting Members', () => {
        cy.loginAdmin("FinancialReports.php");
        cy.contains('Financial Reports');
        cy.get('#FinancialReportTypes').select('Voting Members');
        cy.get('#FinancialReports').submit();
        cy.contains('Financial Reports: Voting Members');
        cy.get('#createReport').click();
    });

    it('Zero Givers', () => {
        cy.loginAdmin("FinancialReports.php");
        cy.contains('Financial Reports');
        cy.get('#FinancialReportTypes').select('Zero Givers');
        cy.get('#FinancialReports').submit();
        cy.contains('Financial Reports: Zero Givers');
        cy.get('#createReport').click();
    });

    it('Individual Deposit Report', () => {
        cy.loginAdmin("FinancialReports.php");
        cy.contains('Financial Reports');
        cy.get('#FinancialReportTypes').select('Individual Deposit Report');
        cy.get('#FinancialReports').submit();
        cy.contains('Financial Reports: Individual Deposit Report');
        cy.get('#createReport').click();
    });

    it('Advanced Deposit Report', () => {
        cy.loginAdmin("FinancialReports.php");
        cy.contains('Financial Reports');
        cy.get('#FinancialReportTypes').select('Advanced Deposit Report');
        cy.get('#FinancialReports').submit();
        cy.contains('Financial Reports: Advanced Deposit Report');
        cy.get('#createReport').click();
    });
});
