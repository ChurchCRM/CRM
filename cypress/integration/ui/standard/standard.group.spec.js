/// <reference types="cypress" />

context('Standard Groups', () => {

   it('Add Group ', () => {

        cy.loginStandard('GroupList.php');
        cy.get('#groupName').type('New Test Group');
        cy.get('#addNewGroup').click();
        cy.get('.odd:nth-child(1) .fa-search-plus').click();
        cy.contains('New Test Group');

    });



    it('Group Report', () => {

        cy.loginStandard('GroupReports.php');
        cy.contains("Group reports");
        cy.contains("Select the group you would like to report")
        cy.get('.box-body > form').submit();
        cy.url().should('contains', 'GroupReports.php');
        cy.contains("Select which information you want to include");
        // TODO cy.get('.box-body > form').submit();

    });

    it('Add and Remove person to group Group ', () => {

        cy.loginStandard("GroupView.php?GroupID=9");
        /*

        TODO: another select 2 issue

        cy.get('.input[aria-controls="select2-addGroupMember"]').type('admin{enter}');
        cy.get('#select2-targetRoleSelection-container').click();
        cy.get('.bootbox-accept').click();
        cy.get('.groupRow:nth-child(2) > td:nth-child(3)').click();
        cy.get('#deleteSelectedRows').click();
        cy.get('.bootbox-accept').click();*/

    });

});
