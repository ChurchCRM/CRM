/// <reference types="cypress" />

describe('Event Type Management', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should display event types list', () => {
        cy.visit('/event/types');
        cy.contains('Event Types').should('exist');
        cy.get('#eventTypesTable').should('exist');
    });

    it('should display times in 12-hour format', () => {
        cy.visit('/event/types');

        cy.get('#eventTypesTable tbody tr').first().within(() => {
            cy.get('td').eq(2).invoke('text').then((timeText) => {
                if (timeText.trim() && timeText.trim() !== '—') {
                    expect(timeText).to.match(/\d{1,2}:\d{2}\s(AM|PM)/);
                }
            });
        });
    });

    it('should navigate to new event type form', () => {
        cy.visit('/event/types');
        cy.contains('a', 'Add Event Type').click();
        cy.url().should('include', '/event/types/new');
    });

    it('should show 3-dropdown time picker on new form', () => {
        cy.visit('/event/types/new');

        cy.get('#newEvtHour').should('exist');
        cy.get('#newEvtMinute').should('exist');
        cy.get('#newEvtPeriod').should('exist');

        cy.get('#newEvtHour option').should('have.length', 12);
        cy.get('#newEvtMinute option').should('have.length', 4);
        cy.get('#newEvtPeriod option').should('have.length', 2);

        // Default should be 9:00 AM
        cy.get('#newEvtHour').should('have.value', '9');
        cy.get('#newEvtMinute').should('have.value', '00');
        cy.get('#newEvtPeriod').should('have.value', 'AM');
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

    it('should display edit page with modern styling', () => {
        cy.visit('/event/types');

        // Click first edit link
        cy.get('#eventTypesTable tbody tr').first().within(() => {
            cy.get('a').first().click({ force: true });
        });

        // Should land on /event/types/{id}
        cy.url().should('match', /\/event\/types\/\d+/);

        // Verify edit page elements
        cy.contains('Edit Event Type').should('exist');
        cy.get('#newEvtName').should('exist');

        // The 3-dropdown time picker (#EventHour / #EventMinute / #EventPeriod) was
        // replaced with a single native <input type="time" id="newEvtStartTime"> +
        // explicit Save button. See commit 46fefb453.
        cy.get('#newEvtStartTime').should('exist').and('have.attr', 'type', 'time');

        // Attendance count rows
        cy.get('[data-cy="attendance-count-row"]').should('exist');
    });
});
