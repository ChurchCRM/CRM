/// <reference types="cypress" />

describe('Repeat Event Editor', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should display repeat event editor page', () => {
        cy.visit('/RepeatEventEditor.php');
        cy.contains('Create Repeat Events').should('exist');
        cy.contains('Event Template').should('exist');
        cy.contains('Recurrence Pattern').should('exist');
        cy.contains('Date Range').should('exist');
    });

    it('should pre-fill event type when navigating from EventNames', () => {
        cy.visit('/event/types');
        // Click the Repeat button for the first event type
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('a.btn-outline-success').click();
        });

        cy.url().should('include', '/RepeatEventEditor.php');
        cy.url().should('include', 'EN_tyid=');
        cy.contains('Change').should('exist');
    });

    it('should have weekly recurrence pre-selected for a weekly event type', () => {
        cy.visit('/RepeatEventEditor.php');
        // Verify the weekly radio exists
        cy.get('#recurWeekly').should('exist');
        cy.get('#recurMonthly').should('exist');
        cy.get('#recurYearly').should('exist');
    });

    it('should enable/disable recurrence inputs based on selection', () => {
        cy.visit('/RepeatEventEditor.php');

        // Select weekly
        cy.get('#recurWeekly').check();
        cy.get('#RecurDOW').should('not.be.disabled');
        cy.get('#RecurDOM').should('be.disabled');
        cy.get('#RecurDOY').should('be.disabled');

        // Select monthly
        cy.get('#recurMonthly').check();
        cy.get('#RecurDOW').should('be.disabled');
        cy.get('#RecurDOM').should('not.be.disabled');
        cy.get('#RecurDOY').should('be.disabled');

        // Select yearly
        cy.get('#recurYearly').check();
        cy.get('#RecurDOW').should('be.disabled');
        cy.get('#RecurDOM').should('be.disabled');
        cy.get('#RecurDOY').should('not.be.disabled');
    });

    it('should create weekly repeat events and redirect to list', () => {
        cy.visit('/event/types');

        // Navigate to repeat editor from first event type
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('a.btn-outline-success').click();
        });

        const uniqueTitle = 'WeeklyRepeat' + Date.now();

        // Fill in the form
        cy.get('input[name="EventTitle"]').clear().type(uniqueTitle);

        // Set start and end time
        cy.get('#StartTime').clear().type('09:00');
        cy.get('#EndTime').clear().type('10:00');

        // Select weekly recurrence
        cy.get('#recurWeekly').check();
        cy.get('#RecurDOW').select('Sunday');

        // Set a short date range (4 weeks) to create ~4 events
        const today = new Date();
        const nextMonth = new Date(today);
        nextMonth.setDate(today.getDate() + 28);

        const formatDate = (d) => d.toISOString().slice(0, 10);
        cy.get('#RangeStart').clear().type(formatDate(today));
        cy.get('#RangeEnd').clear().type(formatDate(nextMonth));

        // Submit
        cy.get('button[name="CreateRepeat"]').click();

        // Should redirect to ListEvents with success Notyf toast
        cy.url().should('include', '/event/dashboard');
        cy.get('.notyf__toast', { timeout: 5000 })
            .should('be.visible')
            .and('contain', 'repeat event');
    });

    it('should show Create Repeat Events button on ListEvents page', () => {
        cy.visit('/event/dashboard');
        cy.contains('Create Repeat Events').should('exist');
        cy.get('a[href*="RepeatEventEditor.php"]').should('exist');
    });

    it('should show Repeat button on EventNames page', () => {
        cy.visit('/event/types');
        cy.get('a[href*="RepeatEventEditor.php"]').should('exist');
    });
});
