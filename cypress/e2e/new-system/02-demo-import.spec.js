/// <reference types="cypress" />

/**
 * Demo Data Import Tests (Steps 4-5)
 * 
 * Tests run after setup wizard completes:
 * 4. Go to admin page and import demo data via UI workflow
 * 5. Ensure system has users and navigates correctly
 * 
 * Prerequisites: Setup wizard must have completed (01-setup-wizard.spec.js)
 */

describe('02 - Demo Data Import', () => {
    const isoDay = (offsetDays) => {
        const day = new Date();
        day.setDate(day.getDate() + offsetDays);
        return day.toISOString().slice(0, 10);
    };

    // Default admin credentials after fresh install
    const adminCredentials = {
        username: 'admin',
        password: 'changeme'
    };

    // Helper function to login.
    // Spec 01 resets the admin password back to 'changeme' before finishing,
    // so we can always use the default credentials here.
    const loginAsAdmin = () => {
        cy.visit('/login');
        cy.get('input[name=User]').type(adminCredentials.username);
        cy.get('input[name=Password]').type(adminCredentials.password + '{enter}');
        cy.url({ timeout: 15000 }).should('not.include', '/session/begin');
    };

    describe('Import Demo Data via Admin UI', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should navigate to admin dashboard', () => {
            cy.visit('/admin/');

            // Should see admin dashboard
            cy.contains('Admin Dashboard').should('be.visible');

            // Should see Quick Start section (demo data moved to /admin/get-started)
            cy.contains('Quick Start').should('be.visible');
        });

        it('should click Import Demo Data button and see confirmation', () => {
            // Demo data import is on the Get Started page
            cy.visit('/admin/get-started');

            // Wait for page to fully load
            cy.get('#importDemoDataV2', { timeout: 10000 }).should('be.visible');

            // Click the Import Demo Data card
            cy.get('#importDemoDataV2').click();
            
            // Should see confirmation overlay
            cy.get('#demoImportModal', { timeout: 5000 }).should('be.visible');
            
            // Should see the confirmation content
            cy.contains('Import Demo Data').should('be.visible');
            
            // Should have confirm and cancel buttons
            cy.get('#demoImportCancelBtn').should('be.focused');
            cy.get('#demoImportConfirmBtn').should('be.visible');
            cy.get('#demoImportCancelBtn').should('be.visible');
        });

        it('should successfully import demo data', () => {
            // Demo data import is on the Get Started page
            cy.visit('/admin/get-started');

            // Click Import Demo Data card
            cy.get('#importDemoDataV2', { timeout: 10000 }).click();
            
            // Wait for confirmation overlay
            cy.get('#demoImportModal', { timeout: 5000 }).should('be.visible');
            
            // Click confirm to start import
            cy.get('#demoImportConfirmBtn').click();
            
            // The modal closes when the import succeeds and stays open with an error when it fails
            cy.get('#demoImportModal', { timeout: 120000 }).should('not.be.visible');
        });
    });

    describe('Verify Demo Data Import', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should have people in the system after demo import', () => {
            // Check people API - use /latest endpoint which returns recent people
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('people');
                // Demo data should have returned people
                expect(response.body.people.length).to.be.greaterThan(0);
                cy.log(`Found ${response.body.people.length} people in latest list`);
            });
        });

        it('should have families in the system after demo import', () => {
            // Check families API - use /latest endpoint which returns recent families
            cy.request({
                method: 'GET',
                url: '/api/families/latest',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('families');
                // Demo data should have returned families
                expect(response.body.families.length).to.be.greaterThan(0);
                cy.log(`Found ${response.body.families.length} families in latest list`);
            });
        });

        it('should import inactive families as deactivated', () => {
            cy.request('/people/family?familyActiveStatus=inactive').its('body').then((html) => {
                expect(Cypress.$(html).find('tbody tr').text()).to.contain('Torres');
            });
            cy.request('/people/family').its('body').then((html) => {
                expect(Cypress.$(html).find('tbody tr').text()).not.to.contain('Torres');
            });
        });

        it('should import inactive people as deactivated', () => {
            cy.request('/people/list?personActiveStatus=inactive').its('body').should('contain', 'Mark King');
            cy.request('/people/list').its('body').should('not.contain', 'Mark King');
        });

        it('should import dateDeceased as deceased', () => {
            cy.request('/people/list').its('body').then((html) => {
                ['Emily Clark', 'Daniel Johnson'].forEach((name) => {
                    expect(html).to.match(new RegExp(`>${name}</a>\\s*<span[^>]*title="Deceased"`));
                });
                expect(html).not.to.match(/>Charles Green<\/a>\s*<span[^>]*title="Deceased"/);
            });
        });

        it('should navigate to home page correctly (not setup)', () => {
            cy.visit('/');
            
            // Should NOT redirect to setup (system is configured)
            cy.url().should('not.include', '/setup');
            
            // Should be on dashboard or some valid page
            cy.url().should('satisfy', (url) => {
                return url.includes('/v2/dashboard') || 
                       url.includes('/admin') || 
                       url.includes('/Menu.php') ||
                       !url.includes('/setup');
            });
        });

        it('should show people listing with demo data', () => {
            cy.visit('/people/list');

            // Should see the people listing
            cy.contains('People').should('be.visible');

            // Should have table with people data + rows populated via DataTables
            // AJAX. The .should('have.length.at.least') auto-retries so no
            // explicit wait is needed.
            cy.get('table', { timeout: 10000 }).should('exist');
            cy.get('table tbody tr', { timeout: 10000 }).should('have.length.at.least', 1);
        });
    });

    describe('Verify Finance Demo Data', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should have deposits in the system after demo import', () => {
            cy.request({
                method: 'GET',
                url: '/api/deposits',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                // /api/deposits returns { Deposits: [...] } (Propel collection format)
                expect(response.body).to.have.property('Deposits');
                expect(response.body.Deposits).to.be.an('array');
                expect(response.body.Deposits.length).to.be.greaterThan(0);
                cy.log(`Found ${response.body.Deposits.length} deposit(s)`);
            });
        });

        it('should have at least one open deposit', () => {
            cy.request({
                method: 'GET',
                url: '/api/deposits',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                // /api/deposits returns { Deposits: [...] } (Propel collection format)
                expect(response.body).to.have.property('Deposits');
                expect(response.body.Deposits).to.be.an('array');

                // Closed is stored as bool (false = open, true = closed)
                const openDeposits = response.body.Deposits.filter(d => !d.Closed);
                expect(openDeposits.length).to.be.greaterThan(0);
                cy.log(`Found ${openDeposits.length} open deposit(s) out of ${response.body.Deposits.length} total`);
            });
        });

        it('should have payments in the system after demo import', () => {
            cy.request({
                method: 'GET',
                url: '/api/deposits',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('Deposits');
                const deposits = response.body.Deposits;
                expect(deposits.length).to.be.greaterThan(0);

                // Use the first deposit (oldest, closed) — it always has payments linked via dep_id
                const depositId = deposits[0].Id;
                return cy.request({
                    method: 'GET',
                    url: `/api/deposits/${depositId}/payments`,
                    timeout: 30000
                });
            }).then((paymentsResponse) => {
                expect(paymentsResponse.status).to.equal(200);
                expect(paymentsResponse.body).to.be.an('array');
                expect(paymentsResponse.body.length).to.be.greaterThan(0);
                cy.log(`Found ${paymentsResponse.body.length} payment(s) in deposit`);
            });
        });

        it('should show finance dashboard after demo import', () => {
            cy.visit('/finance/');

            // Should see the finance dashboard title
            cy.contains('Finance Dashboard').should('be.visible');
        });

    });

    describe('Verify Volunteer Demo Data', () => {
        const DEMO_MINISTRIES = ['Worship', 'Hospitality', "Children's Ministry"];

        const demoStaffing = () => {
            const assignments = [];
            let gaps = 0;
            return cy
                .request(`/api/ministries/occurrences?from=${isoDay(-1)}&to=${isoDay(60)}`)
                .then(({ body }) => {
                    expect(body.occurrences, 'upcoming occurrences').to.have.length.greaterThan(0);
                    body.occurrences.forEach((occurrence) => {
                        cy.request(`/api/ministries/occurrences/${occurrence.id}/staffing`).then(({ body: staffing }) => {
                            staffing.requirements.forEach((requirement) => {
                                gaps += requirement.gapCount;
                                assignments.push(...requirement.assignments);
                            });
                        });
                    });
                })
                .then(() => ({ assignments, gaps }));
        };

        beforeEach(() => {
            loginAsAdmin();
        });

        it('should turn on Volunteer Management V2', () => {
            cy.request('/admin/api/system/config/sVolunteerVersion').its('body.value').should('eq', 'v2');
        });

        it('should create ministries with teams, positions and schedules', () => {
            cy.request('/api/ministries/ministries').then(({ body }) => {
                const ministries = body.ministries.filter((m) => DEMO_MINISTRIES.includes(m.name));
                expect(ministries.map((m) => m.name)).to.have.members(DEMO_MINISTRIES);
                ministries.forEach((ministry) => {
                    cy.request(`/api/ministries/ministries/${ministry.id}`).then(({ body: detail }) => {
                        expect(detail.teams, `${ministry.name} teams`).to.have.length.at.least(2);
                        expect(detail.positions, `${ministry.name} positions`).to.have.length.at.least(2);
                    });
                    cy.request(`/api/ministries/ministries/${ministry.id}/schedules`)
                        .its('body.schedules')
                        .should('have.length.at.least', 1);
                });
            });
        });

        it('should staff the upcoming occurrences with accepted, pending and declined volunteers and leave gaps', () => {
            demoStaffing().then(({ assignments, gaps }) => {
                const statuses = assignments.map((a) => a.status);
                expect(statuses).to.include.members(['accepted', 'pending', 'declined']);
                expect(gaps, 'open positions').to.be.greaterThan(0);
            });
        });

        it('should queue no volunteer email for the demo assignments', () => {
            demoStaffing().then(({ assignments }) => {
                expect(assignments).to.have.length.greaterThan(0);
                assignments.forEach((assignment) => {
                    cy.request(`/api/ministries/assignments/${assignment.id}/notifications`).then(({ body }) => {
                        body.notifications.forEach((notification) => {
                            expect(notification.status, notification.dedupeKey).to.eq('skipped');
                            expect(notification.lastError, notification.dedupeKey).to.eq('demo data import');
                        });
                    });
                });
            });
        });
    });

    describe('Verify Member Portal Demo Data', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should share the demo calendars in the Member Portal', () => {
            cy.request('/admin/api/member-portal/calendars').then(({ body }) => {
                const shared = body.calendars.filter((c) => c.visible).map((c) => c.name);
                expect(shared).to.have.members(['Church Events', 'Youth Ministry', 'Sunday School']);
            });
        });

        it('should show demo events on the portal calendar', () => {
            const from = new Date().toISOString().slice(0, 10);
            cy.request(`/api/portal/calendar/events?from=${from}`).then(({ body }) => {
                expect(body.events.map((e) => e.title)).to.include('Sunday Worship Service');
            });
        });

        it('should hold every Sunday service on a Sunday, whatever day the import ran', () => {
            cy.request(`/api/portal/calendar/events?from=${isoDay(-10)}&to=${isoDay(40)}`).then(({ body }) => {
                const sundayEvents = body.events.filter((e) => e.title.startsWith('Sunday'));
                expect(sundayEvents, 'Sunday services and classes').to.have.length.at.least(5);
                sundayEvents.forEach((event) => {
                    const day = new Date(`${event.start.slice(0, 10)}T12:00:00Z`).getUTCDay();
                    expect(day, `${event.title} on ${event.start}`).to.eq(0);
                });
            });
        });
    });

    describe('Re-run the Demo Import', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should succeed again without duplicating ministries or calendars', () => {
            cy.request({
                method: 'POST',
                url: '/admin/api/demo/load',
                body: { force: true },
                timeout: 300000,
            }).its('body.success').should('eq', true);

            cy.request('/api/ministries/ministries').then(({ body }) => {
                const names = body.ministries.map((m) => m.name);
                expect(names.filter((n) => n === 'Worship')).to.have.length(1);
            });
            cy.request('/admin/api/member-portal/calendars').then(({ body }) => {
                const names = body.calendars.map((c) => c.name);
                expect(names.filter((n) => n === 'Church Events')).to.have.length(1);
            });
        });
    });

    describe('Disable Google Analytics Plugin', () => {
        beforeEach(() => {
            loginAsAdmin();
        });

        it('should disable the Google Analytics plugin from plugin management', () => {
            // Disable via API directly — avoids flaky jQuery click-handler timing
            cy.request({
                method: 'POST',
                url: '/plugins/api/plugins/google-analytics/disable',
                timeout: 30000
            }).then((response) => {
                expect(response.status).to.be.oneOf([200, 204]);
            });

            // Verify the plugin shows as disabled on the management page
            cy.visit('/plugins/management');
            cy.get('.card[data-plugin-id="google-analytics"]', { timeout: 10000 })
                .find('.badge')
                .should('contain', 'Disabled');
        });
    });
});
