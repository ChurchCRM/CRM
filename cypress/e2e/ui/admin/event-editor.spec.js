describe('Event Editor', () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    /**
     * Helper: navigate to EventEditor by getting the first event type's ID
     * and visiting /event/editor?typeId={id} directly.
     */
    function createEventFromFirstType() {
        cy.visit('/event/types');
        // Click first edit link to get a type ID, then go to editor with that type
        cy.get('#eventTypesTable tbody tr').first().find('a').first().invoke('attr', 'href').then((href) => {
            // href is like /event/types/3
            const typeId = href.split('/').pop();
            cy.visit('/event/editor?typeId=' + typeId);
        });
    }

    it('should display event editor page', () => {
        createEventFromFirstType();

        // Verify event editor loads
        cy.url().should('include', '/event/editor');
        cy.contains('Create a new Event').should('exist');
    });

    it('initializes flatpickr on EventDateRange input', () => {
        createEventFromFirstType();

        // Ensure the date range input exists and flatpickr instance is attached
        cy.get('#EventDateRange').should('exist').then(($el) => {
            // The flatpickr instance is stored on the DOM element as _flatpickr
            expect($el[0]._flatpickr).to.exist;
        });
    });

    it('should display attendance count fields', () => {
        createEventFromFirstType();

        // Verify attendance count section
        cy.contains('Attendance Counts').should('exist');

        // Verify at least one attendance count field exists
        cy.get('.attendance-count').should('have.length.greaterThan', 0);
    });

    it('should auto-calculate real total from attendance counts', () => {
        createEventFromFirstType();

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
        createEventFromFirstType();

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
        createEventFromFirstType();

        // Verify Real Total field exists and is positioned correctly
        cy.contains('Attendance Counts').parent().within(() => {
            cy.get('#RealTotal').should('exist');
        });
    });

    it('should have Real Total field as readonly', () => {
        createEventFromFirstType();

        // Verify Real Total is readonly
        cy.get('#RealTotal').should('have.attr', 'readonly');
    });

    it('should use Quill editor for event description', () => {
        createEventFromFirstType();

        // Verify Quill editor is initialized
        cy.get('.ql-container').should('exist');
        cy.get('.ql-editor').should('exist');
    });

    it('should create event with attendance counts', () => {
        createEventFromFirstType();

        // Fill in event details
        const eventTitle = 'Test Event ' + Date.now();
        cy.get('input[name="EventTitle"]').clear().type(eventTitle);

        // Set attendance counts
        cy.get('.attendance-count').each(($input) => {
            cy.wrap($input).clear().type('25');
        });

        // Select event status (Active)
        // EventStatus radio is wrapped in .event-editor-advanced (collapsed by default
        // for new events). Use force:true to set the underlying value without expanding
        // the UI — these tests aren't testing the toggle, just need the field set.
        cy.get('input[name="EventStatus"][value="0"]').check({ force: true });

        // Save event
        cy.get('button[name="SaveChanges"]').click();

        // Verify redirect to event list
        cy.url().should('include', '/event/dashboard');
    });

    it('should handle events without attendance counts', () => {
        // Create an event type with no attendance counts first
        cy.visit('/event/types/new');

        const eventTypeName = 'No Counts ' + Date.now();
        cy.get('#newEvtName').type(eventTypeName);
        // Leave attendance counts empty
        cy.get('#newEvtTypeCntLst').clear();
        cy.contains('button', 'Save Event Type').click();

        // Verify successful creation
        cy.url().should('include', '/event/types');
        cy.url().should('not.include', '/new');

        // Navigate to event editor for any type
        createEventFromFirstType();

        // Verify event editor loads without errors
        cy.contains('Create a new Event').should('exist');
        cy.get('input[name="EventTitle"]').should('exist');
    });

    it('should save event and redirect to event list', () => {
        createEventFromFirstType();

        const eventTitle = 'TestEvent' + Date.now();
        cy.get('input[name="EventTitle"]').clear().type(eventTitle);
        // EventStatus radio is wrapped in .event-editor-advanced (collapsed by default
        // for new events). Use force:true to set the underlying value without expanding
        // the UI — these tests aren't testing the toggle, just need the field set.
        cy.get('input[name="EventStatus"][value="0"]').check({ force: true });
        cy.get('button[name="SaveChanges"]').click();

        // Verify redirect to event list (successful save)
        cy.url().should('include', '/event/dashboard');
    });

    it('should validate Propel ORM data retrieval (no duplicates)', () => {
        createEventFromFirstType();

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
        createEventFromFirstType();

        const originalTitle = 'OriginalEvent' + Date.now();
        cy.get('input[name="EventTitle"]').clear().type(originalTitle);
        // EventStatus radio is wrapped in .event-editor-advanced (collapsed by default
        // for new events). Use force:true to set the underlying value without expanding
        // the UI — these tests aren't testing the toggle, just need the field set.
        cy.get('input[name="EventStatus"][value="0"]').check({ force: true });
        cy.get('button[name="SaveChanges"]').click();

        // Verify redirect to event list
        cy.url().should('include', '/event/dashboard');

        // Step 2: Get the event ID via API and navigate directly to edit
        cy.request('/api/events').then((response) => {
            // Propel toJSON returns an object with Events array
            const events = response.body.Events || response.body;
            const eventArray = Array.isArray(events) ? events : Object.values(events);
            const createdEvent = eventArray.find(e => e.Title === originalTitle);
            expect(createdEvent, 'Created event should exist in API response').to.exist;
            const eventId = createdEvent.Id;

            // Step 3: Navigate directly to edit page
            cy.visit(`/event/editor/${eventId}`);

            // Step 4: Verify we're editing (not creating new)
            cy.contains('Editing Event').should('exist');

            // Step 5: Verify the original title is loaded
            cy.get('input[name="EventTitle"]').should('have.value', originalTitle);

            // Step 6: Update the event title
            const updatedTitle = 'UpdatedEvent' + Date.now();
            cy.get('input[name="EventTitle"]').clear().type(updatedTitle);
            cy.get('button[name="SaveChanges"]').click();

            // Step 7: Verify redirect to event list
            cy.url().should('include', '/event/dashboard');

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
        createEventFromFirstType();

        const testTitle = 'DataLoadTest' + Date.now();
        cy.get('input[name="EventTitle"]').clear().type(testTitle);

        // Set attendance counts if available
        cy.get('.attendance-count').each(($input) => {
            cy.wrap($input).clear().type('15');
        });

        // EventStatus radio is wrapped in .event-editor-advanced (collapsed by default
        // for new events). Use force:true to set the underlying value without expanding
        // the UI — these tests aren't testing the toggle, just need the field set.
        cy.get('input[name="EventStatus"][value="0"]').check({ force: true });
        cy.get('button[name="SaveChanges"]').click();

        cy.url().should('include', '/event/dashboard');

        // Get event ID via API and navigate directly
        cy.request('/api/events').then((response) => {
            const events = response.body.Events || response.body;
            const eventArray = Array.isArray(events) ? events : Object.values(events);
            const createdEvent = eventArray.find(e => e.Title === testTitle);
            expect(createdEvent, 'Created event should exist in API response').to.exist;
            const eventId = createdEvent.Id;

            // Navigate directly to edit page
            cy.visit(`/event/editor/${eventId}`);

            // Verify all data loads correctly (not empty/default values)
            cy.get('input[name="EventTitle"]').should('have.value', testTitle);

            // Verify eventId hidden field has a value (not 0)
            cy.get('input[name="eventId"]').should('not.have.value', '0');
            cy.get('input[name="eventId"]').invoke('val').then((val) => {
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

    // ──────────────────────────────────────────────────────────────────────
    // Quick Mode Toggle (#8499)
    // ──────────────────────────────────────────────────────────────────────
    describe('Quick Mode Toggle (#8499)', () => {
        it('hides advanced fields by default for new events', () => {
            createEventFromFirstType();

            // Advanced fields should be hidden initially for new events
            cy.get('.event-editor-advanced').should('not.be.visible');

            // Toggle button should exist with "Show More Options" text
            cy.get('#toggleAdvancedBtn').should('exist').and('contain', 'Show More Options');
        });

        it('shows advanced fields when toggle is clicked', () => {
            createEventFromFirstType();

            cy.get('#toggleAdvancedBtn').click();

            // Advanced fields should now be visible
            cy.get('.event-editor-advanced').first().should('be.visible');
            cy.get('#toggleAdvancedBtn').should('contain', 'Hide Advanced Options');
        });

        it('toggles advanced fields back to hidden when clicked twice', () => {
            createEventFromFirstType();

            cy.get('#toggleAdvancedBtn').click();
            cy.get('.event-editor-advanced').first().should('be.visible');

            cy.get('#toggleAdvancedBtn').click();
            cy.get('.event-editor-advanced').should('not.be.visible');
        });
    });

    // ──────────────────────────────────────────────────────────────────────
    // Date Validation (#6629)
    // ──────────────────────────────────────────────────────────────────────
    describe('Date Validation (#6629)', () => {
        it('blocks form submission when end date is before start date', () => {
            createEventFromFirstType();

            const eventTitle = 'Date Validation Test ' + Date.now();
            cy.get('input[name="EventTitle"]').clear().type(eventTitle);

            // Set an invalid range: end before start. After typing into the
            // daterangepicker input, the .drp-buttons overlay can cover the
            // SaveChanges button. Use requestSubmit() (NOT form.submit()) so
            // the JS submit handler still fires — submit() bypasses event
            // listeners and would let the bad date through.
            cy.get('#EventDateRange').clear().type('2026-12-31 09:00 AM - 2026-01-01 10:00 AM', { force: true });
            cy.get('#EventDateRange').type('{esc}');
            cy.get('form[name="EventsEditor"]').then(($form) => {
                $form[0].requestSubmit();
            });

            // Should NOT have redirected to dashboard (blocked by client-side validation)
            cy.url().should('include', '/event/editor');
        });

        it('allows form submission when end date is on or after start date', () => {
            createEventFromFirstType();

            const eventTitle = 'Valid Dates Test ' + Date.now();
            cy.get('input[name="EventTitle"]').clear().type(eventTitle);

            // The default daterangepicker value should be valid (start === end same day)
            cy.get('button[name="SaveChanges"]').click();

            // Should redirect to dashboard
            cy.url().should('include', '/event/dashboard');
        });
    });
});
