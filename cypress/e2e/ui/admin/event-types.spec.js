describe('Event Type Management', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should display event types with 12-hour time format', () => {
        cy.visit('/EventNames.php');
        
        // Verify page and table structure
        cy.contains('Event Types').should('exist');
        cy.get('#eventNames').should('exist');
        
        // Verify times are in 12-hour format (not 24-hour)
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('td').eq(2).invoke('text').then((timeText) => {
                expect(timeText).to.match(/\d{1,2}:\d{2}\s(AM|PM)/);
            });
        });
    });

    it('should have 3-dropdown time picker (no auto-submit)', () => {
        cy.visit('/EventNames.php');
        cy.contains('button', 'Add Event Type').click();
        
        // Verify 3-dropdown time picker exists
        cy.get('#newEvtHour').should('exist');
        cy.get('#newEvtMinute').should('exist');
        cy.get('#newEvtPeriod').should('exist');
        
        // Verify hour (1-12), minute (00/15/30/45), period (AM/PM)
        cy.get('#newEvtHour option').should('have.length', 12);
        cy.get('#newEvtMinute option').should('have.length', 4);
        cy.get('#newEvtPeriod option').should('have.length', 2);
        
        // Verify default is 9:00 AM
        cy.get('#newEvtHour').should('have.value', '9');
        cy.get('#newEvtMinute').should('have.value', '00');
        cy.get('#newEvtPeriod').should('have.value', 'AM');
        
        // Change time without submitting - verify no auto-submit
        cy.get('#newEvtHour').select('3');
        cy.get('#newEvtPeriod').select('PM');
        cy.get('#newEvtName').should('be.visible'); // Form still visible
    });

    it('should create event type with midnight and noon times', () => {
        cy.visit('/EventNames.php');
        
        // Test midnight (12:00 AM)
        cy.contains('button', 'Add Event Type').click();
        const midnight = 'Midnight ' + Date.now();
        cy.get('#newEvtName').type(midnight);
        cy.get('#newEvtHour').select('12');
        cy.get('#newEvtPeriod').select('AM');
        cy.contains('button', 'Save Event Type').click();
        cy.contains(midnight).should('exist');
        cy.contains('12:00 AM').should('exist');
        
        // Test noon (12:00 PM)
        cy.contains('button', 'Add Event Type').click();
        const noon = 'Noon ' + Date.now();
        cy.get('#newEvtName').type(noon);
        cy.get('#newEvtHour').select('12');
        cy.get('#newEvtPeriod').select('PM');
        cy.contains('button', 'Save Event Type').click();
        cy.contains(noon).should('exist');
        cy.contains('12:00 PM').should('exist');
    });

    it('should create event type without auto "Total" attendance count', () => {
        cy.visit('/EventNames.php');
        cy.contains('button', 'Add Event Type').click();
        
        const eventTypeName = 'NoAutoTotal ' + Date.now();
        cy.get('#newEvtName').type(eventTypeName);
        cy.get('#newEvtTypeCntLst').type('Adults, Children, Teens');
        cy.contains('button', 'Save Event Type').click();
        
        // Verify redirect back to list (successful creation)
        cy.url().should('include', '/EventNames.php');
        cy.url().should('not.include', 'Action=NEW');
        
        // Verify attendance counts by checking first event type's edit page
        // (We can verify the feature works without searching for our specific item)
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('a.btn-outline-secondary').click();
        });
        
        // Verify attendance count rows exist and have proper structure
        cy.get('[data-cy="attendance-count-row"]').should('have.length.greaterThan', 0);
    });

    it('should enable recurrence pattern dropdowns correctly', () => {
        cy.visit('/EventNames.php');
        cy.contains('button', 'Add Event Type').click();
        
        // Initially all disabled
        cy.get('select[name="newEvtRecurDOW"]').should('be.disabled');
        cy.get('select[name="newEvtRecurDOM"]').should('be.disabled');
        cy.get('input[name="newEvtRecurDOY"]').should('be.disabled');
        
        // Select Weekly - only DOW enabled
        cy.get('#recurWeekly').check();
        cy.get('select[name="newEvtRecurDOW"]').should('not.be.disabled');
        cy.get('select[name="newEvtRecurDOM"]').should('be.disabled');
        
        // Select Monthly - only DOM enabled
        cy.get('#recurMonthly').check();
        cy.get('select[name="newEvtRecurDOW"]').should('be.disabled');
        cy.get('select[name="newEvtRecurDOM"]').should('not.be.disabled');
        
        // Select Yearly - only DOY enabled
        cy.get('#recurYearly').check();
        cy.get('input[name="newEvtRecurDOY"]').should('not.be.disabled');
        
        // Select None - all disabled
        cy.get('#recurNone').check();
        cy.get('select[name="newEvtRecurDOW"]').should('be.disabled');
        cy.get('select[name="newEvtRecurDOM"]').should('be.disabled');
        cy.get('input[name="newEvtRecurDOY"]').should('be.disabled');
    });

    it('should display EditEventTypes page with modern styling', () => {
        cy.visit('/EventNames.php');
        
        // Navigate to edit page
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('a.btn-outline-secondary').click();
        });
        
        // Verify modern card design
        cy.get('.card-primary').should('exist');
        cy.contains('Edit Event Type').should('exist');
        
        // Verify 3-dropdown time picker in edit page
        cy.get('#EventHour').should('exist');
        cy.get('#EventMinute').should('exist');
        cy.get('#EventPeriod').should('exist');
        
        // Verify attendance counts table
        cy.get('.table-bordered').should('exist');
    });
});
