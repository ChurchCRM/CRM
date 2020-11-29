/// <reference types="cypress" />

context('Standard Calendar', () => {

    it('Create New Event', () => {
        let title = "My New Event - " + Cypress._.random(0, 1e6);
        cy.loginStandard("v2/calendar");
        cy.get('.fc-row:nth-child(1) > .fc-content-skeleton .fc-thu').click();
        cy.get('.modal-header > input').click();
        cy.get('.modal-header > input').type(title);
        cy.get('tr:nth-child(2) textarea').type('New adult Service');
        cy.get('tr:nth-child(6) textarea').type('Come join us');
        //cy.get('#PinnedCalendars').type('Public Calendar');
        //cy.get('.btn-success').click();


    });

});
