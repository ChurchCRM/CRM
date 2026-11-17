/// <reference types="cypress" />

describe('Event Type Management', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    /**
     * Filter the eventTypesTable DataTable to a specific search string.
     *
     * DataTables 2.x with the CRM `layout` config doesn't render the legacy
     * `#tableId_filter input[type=search]` element, so the selector-based
     * approach used elsewhere doesn't work here. Instead drive the DataTable
     * JS API directly — selector-independent and works with any layout.
     *
     * Without filtering, default 10-row pagination drops new rows off page 1
     * once enough types accumulate, and `cy.contains` can't find them.
     */
    function filterEventTypesTable(query) {
        cy.window().then((win) => {
            const dt = win.$('#eventTypesTable').DataTable();
            dt.search(query).draw();
        });
        cy.get('#eventTypesTable tbody', { timeout: 20000 }).should('contain', query);
    }

    it('should create event type with midnight (12:00 AM)', () => {
        cy.visit('/event/types/new');

        const midnight = 'Midnight ' + Date.now();
        cy.get('#newEvtName').type(midnight);
        cy.get('#newEvtHour').select('12');
        cy.get('#newEvtPeriod').select('AM');
        cy.contains('button', 'Save Event Type').click();

        cy.location('pathname', { timeout: 10000 }).should('match', /\/event\/types\/?$/);
        cy.get('#eventTypesTable', { timeout: 10000 }).should('exist');
        filterEventTypesTable(midnight);
        cy.get('#eventTypesTable tbody tr', { timeout: 10000 }).should('have.length', 1);
        cy.get('#eventTypesTable tbody tr').should('contain', midnight).and('contain', '12:00 AM');
    });

    it('should create event type with noon (12:00 PM)', () => {
        cy.visit('/event/types/new');

        const noon = 'Noon ' + Date.now();
        cy.get('#newEvtName').type(noon);
        cy.get('#newEvtHour').select('12');
        cy.get('#newEvtPeriod').select('PM');
        cy.contains('button', 'Save Event Type').click();

        cy.location('pathname', { timeout: 10000 }).should('match', /\/event\/types\/?$/);
        cy.get('#eventTypesTable', { timeout: 10000 }).should('exist');
        filterEventTypesTable(noon);
        cy.get('#eventTypesTable tbody tr', { timeout: 10000 }).should('have.length', 1);
        cy.get('#eventTypesTable tbody tr').should('contain', noon).and('contain', '12:00 PM');
    });

    it('should create event type with attendance counts', () => {
        cy.visit('/event/types/new');

        const eventTypeName = 'CountTest ' + Date.now();
        cy.get('#newEvtName').type(eventTypeName);
        cy.get('#newEvtTypeCntLst').type('Adults, Children, Teens');
        cy.contains('button', 'Save Event Type').click();

        // Should redirect to types list
        cy.url().should('include', '/event/types');
        cy.url().should('not.include', '/new');

        cy.get('#eventTypesTable', { timeout: 10000 }).should('exist');
        filterEventTypesTable(eventTypeName);
        cy.get('#eventTypesTable tbody tr', { timeout: 10000 }).should('have.length', 1).and('contain', eventTypeName);
    });

    it('should enable recurrence pattern dropdowns correctly', () => {
        cy.visit('/event/types/new');

        // Initially all disabled
        cy.get('select[name="newEvtRecurDOW"]').should('be.disabled');
        cy.get('select[name="newEvtRecurDOM"]').should('be.disabled');
        cy.get('input[name="newEvtRecurDOY"]').should('be.disabled');

        // Weekly
        cy.get('#recurWeekly').check();
        cy.get('select[name="newEvtRecurDOW"]').should('not.be.disabled');
        cy.get('select[name="newEvtRecurDOM"]').should('be.disabled');

        // Monthly
        cy.get('#recurMonthly').check();
        cy.get('select[name="newEvtRecurDOW"]').should('be.disabled');
        cy.get('select[name="newEvtRecurDOM"]').should('not.be.disabled');

        // Yearly
        cy.get('#recurYearly').check();
        cy.get('input[name="newEvtRecurDOY"]').should('not.be.disabled');

        // None
        cy.get('#recurNone').check();
        cy.get('select[name="newEvtRecurDOW"]').should('be.disabled');
        cy.get('select[name="newEvtRecurDOM"]').should('be.disabled');
        cy.get('input[name="newEvtRecurDOY"]').should('be.disabled');
    });

});
