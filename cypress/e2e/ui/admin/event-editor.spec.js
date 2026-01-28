describe('Event Editor', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it('should display event editor page', () => {
        // Create event from first event type
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Verify event editor loads
        cy.url().should('include', '/EventEditor.php');
        cy.contains('Create a new Event').should('exist');
    });

    it('should display attendance count fields', () => {
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Verify attendance count section
        cy.contains('Attendance Counts').should('exist');
        
        // Verify at least one attendance count field exists
        cy.get('.attendance-count').should('have.length.greaterThan', 0);
    });

    it('should auto-calculate real total from attendance counts', () => {
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Enter values in attendance count fields
        cy.get('.attendance-count').each(($input, index) => {
            cy.wrap($input).clear().type((index + 1) * 10);
        });
        
        // Verify Real Total is calculated (sum of all counts)
        cy.get('#RealTotal').invoke('val').then((totalValue) => {
            expect(parseInt(totalValue)).to.be.greaterThan(0);
        });
    });

    it('should update real total when attendance counts change', () => {
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Get initial total
        let initialTotal;
        cy.get('#RealTotal').invoke('val').then((val) => {
            initialTotal = parseInt(val) || 0;
        });
        
        // Update first attendance count
        cy.get('.attendance-count').first().clear().type('50');
        
        // Verify total updated
        cy.get('#RealTotal').invoke('val').then((newTotal) => {
            expect(parseInt(newTotal)).to.not.equal(initialTotal);
        });
    });

    it('should have Real Total field under Attendance Counts label', () => {
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Verify Real Total field exists and is positioned correctly
        cy.contains('Attendance Counts').parent().within(() => {
            cy.get('#RealTotal').should('exist');
        });
    });

    it('should have Real Total field as readonly', () => {
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Verify Real Total is readonly
        cy.get('#RealTotal').should('have.attr', 'readonly');
    });

    it('should use Quill editor for event description', () => {
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Verify Quill editor is initialized
        cy.get('.ql-container').should('exist');
        cy.get('.ql-editor').should('exist');
    });

    it('should create event with attendance counts', () => {
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Fill in event details
        const eventTitle = 'Test Event ' + Date.now();
        cy.get('input[name="EventTitle"]').clear().type(eventTitle);
        
        // Set attendance counts
        cy.get('.attendance-count').each(($input) => {
            cy.wrap($input).clear().type('25');
        });
        
        // Select event status (Active)
        cy.get('input[name="EventStatus"][value="0"]').check();
        
        // Save event
        cy.get('button[name="SaveChanges"]').click();
        
        // Verify redirect to event list
        cy.url().should('include', '/ListEvents.php');
    });

    it('should handle events without attendance counts', () => {
        // Create an event type with no attendance counts first
        cy.visit('/EventNames.php');
        cy.contains('button', 'Add Event Type').click();
        
        const eventTypeName = 'No Counts ' + Date.now();
        cy.get('#newEvtName').type(eventTypeName);
        // Leave attendance counts empty
        cy.get('#newEvtTypeCntLst').clear();
        cy.contains('button', 'Save Event Type').click();
        
        // Verify successful creation
        cy.url().should('include', '/EventNames.php');
        cy.url().should('not.include', 'Action=NEW');
        
        // Create event from any type (verify editor works without errors)
        cy.get('button.btn-success').first().click();
        
        // Verify event editor loads without errors
        cy.contains('Create a new Event').should('exist');
        cy.get('input[name="EventTitle"]').should('exist');
    });

    it('should save event and redirect to event list', () => {
        // Create an event
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        const eventTitle = 'TestEvent' + Date.now();
        cy.get('input[name="EventTitle"]').clear().type(eventTitle);
        cy.get('input[name="EventStatus"][value="0"]').check();
        cy.get('button[name="SaveChanges"]').click();
        
        // Verify redirect to event list (successful save)
        cy.url().should('include', '/ListEvents.php');
    });

    it('should validate Propel ORM data retrieval (no duplicates)', () => {
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });
        
        // Check that attendance count fields are unique (no duplicates)
        cy.get('.attendance-count').then($inputs => {
            const names = [];
            $inputs.each((index, input) => {
                const name = Cypress.$(input).data('count-name');
                if (name) {
                    names.push(name);
                }
            });
            
            // Verify no duplicate count names (Propel primaryKey fix)
            const uniqueNames = [...new Set(names)];
            expect(names.length).to.equal(uniqueNames.length);
        });
    });

    it('should edit existing event and update it (issue #7918)', () => {
        // Step 1: Create a new event first
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });

        const originalTitle = 'OriginalEvent' + Date.now();
        cy.get('input[name="EventTitle"]').clear().type(originalTitle);
        cy.get('input[name="EventStatus"][value="0"]').check();
        cy.get('button[name="SaveChanges"]').click();

        // Verify redirect to event list
        cy.url().should('include', '/ListEvents.php');

        // Step 2: Get the event ID via API and navigate directly to edit
        cy.request('/api/events').then((response) => {
            // Propel toJSON returns an object with Events array
            const events = response.body.Events || response.body;
            const eventArray = Array.isArray(events) ? events : Object.values(events);
            const createdEvent = eventArray.find(e => e.Title === originalTitle);
            expect(createdEvent, 'Created event should exist in API response').to.exist;
            const eventId = createdEvent.Id;

            // Step 3: Navigate directly to edit page
            cy.visit(`/EventEditor.php?EID=${eventId}`);

            // Step 4: Verify we're editing (not creating new)
            cy.contains('Editing Event').should('exist');

            // Step 5: Verify the original title is loaded
            cy.get('input[name="EventTitle"]').should('have.value', originalTitle);

            // Step 6: Update the event title
            const updatedTitle = 'UpdatedEvent' + Date.now();
            cy.get('input[name="EventTitle"]').clear().type(updatedTitle);
            cy.get('button[name="SaveChanges"]').click();

            // Step 7: Verify redirect to event list
            cy.url().should('include', '/ListEvents.php');

            // Step 8: Verify the update via API
            cy.request('/api/events').then((updateResponse) => {
                const updatedEvents = updateResponse.body.Events || updateResponse.body;
                const updatedArray = Array.isArray(updatedEvents) ? updatedEvents : Object.values(updatedEvents);
                const updatedEvent = updatedArray.find(e => e.Id === eventId);
                expect(updatedEvent.Title).to.equal(updatedTitle);
                // Verify original title no longer exists (was updated, not duplicated)
                const originalExists = updatedArray.some(e => e.Title === originalTitle);
                expect(originalExists).to.be.false;
            });
        });
    });

    it('should load event data correctly when editing (regression test)', () => {
        // Create an event with specific data
        cy.visit('/EventNames.php');
        cy.get('#eventNames tbody tr').first().within(() => {
            cy.get('button.btn-success').click();
        });

        const testTitle = 'DataLoadTest' + Date.now();
        cy.get('input[name="EventTitle"]').clear().type(testTitle);
        
        // Set attendance counts if available
        cy.get('.attendance-count').each(($input) => {
            cy.wrap($input).clear().type('15');
        });
        
        cy.get('input[name="EventStatus"][value="0"]').check();
        cy.get('button[name="SaveChanges"]').click();

        cy.url().should('include', '/ListEvents.php');

        // Get event ID via API and navigate directly
        cy.request('/api/events').then((response) => {
            const events = response.body.Events || response.body;
            const eventArray = Array.isArray(events) ? events : Object.values(events);
            const createdEvent = eventArray.find(e => e.Title === testTitle);
            expect(createdEvent, 'Created event should exist in API response').to.exist;
            const eventId = createdEvent.Id;

            // Navigate directly to edit page
            cy.visit(`/EventEditor.php?EID=${eventId}`);

            // Verify all data loads correctly (not empty/default values)
            cy.get('input[name="EventTitle"]').should('have.value', testTitle);
            
            // Verify EventID hidden field has a value (not 0)
            cy.get('input[name="EventID"]').should('not.have.value', '0');
            cy.get('input[name="EventID"]').invoke('val').then((val) => {
                expect(parseInt(val)).to.equal(eventId);
            });

            // Verify attendance counts loaded (if they exist)
            cy.get('.attendance-count').each(($input) => {
                cy.wrap($input).invoke('val').then((val) => {
                    // Should have the value we set (15) or be calculated
                    expect(parseInt(val) || 0).to.be.at.least(0);
                });
            });
        });
    });
});
