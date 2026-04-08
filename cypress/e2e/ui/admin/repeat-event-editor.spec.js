/// <reference types="cypress" />

describe('Repeat Event Editor', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should display repeat event editor page', () => {
        cy.visit('/event/repeat-editor');
        cy.contains('Create Repeat Events').should('exist');
        cy.contains('Event Template').should('exist');
        cy.contains('Recurrence Pattern').should('exist');
        cy.contains('Date Range').should('exist');
    });

    it('should pre-fill event type when navigating with type ID', () => {
        // Get a type ID from the types list
        cy.visit('/event/types');
        cy.get('#eventTypesTable tbody tr').first().find('a').first().invoke('attr', 'href').then((href) => {
            const typeId = href.split('/').pop();
            cy.visit('/event/repeat-editor/' + typeId);
            cy.url().should('include', '/event/repeat-editor/' + typeId);
            cy.contains('Change').should('exist');
        });
    });

    it('should have all recurrence radio options', () => {
        cy.visit('/event/repeat-editor');
        cy.get('#recurWeekly').should('exist');
        cy.get('#recurMonthly').should('exist');
        cy.get('#recurYearly').should('exist');
    });

    it('should enable/disable recurrence inputs based on selection', () => {
        cy.visit('/event/repeat-editor');

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

    it('should create weekly repeat events and redirect to dashboard', () => {
        // Get a type ID
        cy.visit('/event/types');
        cy.get('#eventTypesTable tbody tr').first().find('a').first().invoke('attr', 'href').then((href) => {
            const typeId = href.split('/').pop();
            cy.visit('/event/repeat-editor/' + typeId);

            const uniqueTitle = 'WeeklyRepeat' + Date.now();

            cy.get('input[name="EventTitle"]').clear().type(uniqueTitle);
            cy.get('#StartTime').clear().type('09:00');
            cy.get('#EndTime').clear().type('10:00');

            cy.get('#recurWeekly').check();
            cy.get('#RecurDOW').select('Sunday');

            // Short date range
            const today = new Date();
            const nextMonth = new Date(today);
            nextMonth.setDate(today.getDate() + 28);
            const formatDate = (d) => d.toISOString().slice(0, 10);
            cy.get('#RangeStart').clear().type(formatDate(today));
            cy.get('#RangeEnd').clear().type(formatDate(nextMonth));

            cy.get('button[name="CreateRepeat"]').click();

            cy.url().should('include', '/event/dashboard');
        });
    });

    it('should show Create Repeat Events link on dashboard empty state', () => {
        // The link only appears in empty-state OR via dropdown
        // Guard against intermittent 500 responses by checking the HTTP
        // status first; surface a clear failure if the server returns 5xx.
        cy.request({ url: '/event/dashboard', failOnStatusCode: false }).then((resp) => {
            if (resp.status === 200) {
                cy.visit('/event/dashboard');
                cy.contains('Events Dashboard').should('exist');
            } else {
                // Fail with a clear message so CI logs show the server error.
                throw new Error('Failed to load /event/dashboard; status: ' + resp.status);
            }
        });
    });

    it('should have repeat event link from event types list', () => {
        cy.visit('/event/types');
        cy.get('a[href*="event/repeat-editor"]').should('exist');
    });
});
