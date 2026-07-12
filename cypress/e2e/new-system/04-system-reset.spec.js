/// <reference types="cypress" />

/**
 * System Reset Tests (Step 10)
 * 
 * Tests the complete reset workflow:
 * 10. Do a system reset, login, ensure everything is blank
 * 
 * Prerequisites: Previous tests must have run (backup/restore completed)
 * 
 * After reset:
 * - All database tables are recreated
 * - Config.php persists (no setup wizard)
 * - System starts fresh with admin/changeme credentials
 */

describe('04 - System Reset', () => {
    // Default admin credentials
    const adminCredentials = {
        username: 'admin',
        password: 'changeme'
    };

    // Helper to manually login, handling forced password-change redirect after a DB reset
    const manualLogin = () => {
        cy.clearCookies();
        cy.clearLocalStorage();
        // Admin password is 'changeme'. After a DB reset NeedPasswordChange=true,
        // which forces a redirect to /changepassword on first login.
        const password = adminCredentials.password;
        cy.visit('/login');
        cy.get('input[name=User]', { timeout: 15000 }).type(adminCredentials.username);
        cy.get('input[name=Password]').type(password);
        cy.get('input[name=Password]').type('{enter}');
        cy.url({ timeout: 30000 }).should('not.include', '/session/begin');

        // After a DB reset the admin has NeedPasswordChange=true; complete the forced form if needed.
        // The forced form uses button[type=submit] (login-box layout, not card layout).
        cy.url().then((url) => {
            if (url.includes('/changepassword')) {
                cy.get('#OldPassword').type(password);
                cy.get('#NewPassword1').type('Cypress@01!');
                cy.get('#NewPassword2').type('Cypress@01!');
                cy.get('button[type=submit]').click();
                // ChurchInfoRequiredMiddleware redirects to church-info when sChurchName is empty
                cy.url({ timeout: 15000 }).should('include', '/admin/system/church-info');
            }
        });

        // After a DB reset sChurchName is empty; fill in the minimum required fields so the
        // middleware stops redirecting and subsequent test navigation works normally.
        cy.url().then((url) => {
            if (url.includes('/admin/system/church-info')) {
                // Wait for page to fully load — country defaults to US and populates state dropdown
                cy.get('#sChurchCountry', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
                cy.get('#sChurchName').clear().type('Test Community Church');
                cy.get('#sChurchPhone').clear().type('(555) 123-4567');
                cy.get('#sChurchEmail').clear().type('info@testchurch.org');
                cy.get('#sChurchAddress').clear().type('123 Main Street');
                cy.get('#sChurchCity').clear().type('Springfield');
                // Country defaults to US — wait for state dropdown then verify value is set
                cy.get('#sChurchState', { timeout: 10000 }).siblings('.ts-wrapper').should('exist');
                cy.tomSelectByValue('#sChurchState', 'IL');
                cy.get('#sChurchState').should('have.value', 'IL');
                cy.get('#sChurchZip').clear().type('62701');
                cy.get('#church-info-form').submit();
                cy.url({ timeout: 10000 }).should('include', 'church-info');
            }
        });
    };

    describe('Step 10a: Navigate to Reset Page', () => {
        it('should display danger warning and reset card', () => {
            manualLogin();
            cy.visit('/admin/system/reset');

            // Danger banner at top — scope to the top warning banner so it
            // doesn't match hidden backup status alerts also in the DOM.
            cy.contains('.alert-danger', 'Destructive Operation', { timeout: 15000 })
                .should('be.visible');

            // Reset button should be disabled until user types RESET
            cy.get('#resetBtn').should('be.disabled');
        });

        it('should enable reset button after typing RESET', () => {
            manualLogin();
            cy.visit('/admin/system/reset');

            cy.get('#confirmInput', { timeout: 15000 }).type('RESET');
            cy.get('#resetBtn').should('not.be.disabled');
        });
    });

    describe('Step 10b: Perform System Reset', () => {
        /**
         * Regression test for GHSA-r68j-h5c6-w6gh — DB reset must delete uploaded photos.
         *
         * Before the fix, the reset endpoint used lowercase '/Images/person' and
         * '/Images/family' while uploaded photos are stored in uppercase '/Images/Person'
         * and '/Images/Family'. On Linux (case-sensitive FS) the reset silently left all
         * uploaded photos on disk even though it reported success.
         *
         * This test:
         *   1. Picks the first person from the pre-reset database.
         *   2. Uploads a 1-pixel PNG photo to that person.
         *   3. Confirms the photo file is accessible at /Images/Person/{id}.png.
         *   4. Calls the DB reset endpoint.
         *   5. Immediately confirms the photo file returns 404 — meaning it was
         *      removed from disk by the reset handler.
         *
         * The photo URL does not require authentication, so step 5 works without
         * a valid session even after the reset destroys the current session.
         */
        it('should delete uploaded person photos during database reset (GHSA-r68j-h5c6-w6gh)', () => {
            manualLogin();

            // 1. Get the first person in the database to use as our test subject.
            cy.request({
                method: 'GET',
                url: '/api/persons/latest',
                timeout: 15000
            }).then((listResp) => {
                expect(listResp.status).to.equal(200);
                expect(listResp.body.people.length).to.be.greaterThan(0);
                const personId = listResp.body.people[0].PersonId;
                cy.log(`Using person ID ${personId} for photo cleanup regression test`);

                // 2. Upload a minimal 1x1 PNG so a photo file is written to Images/Person/.
                const base64Photo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
                cy.request({
                    method: 'POST',
                    url: `/api/person/${personId}/photo`,
                    body: JSON.stringify({ imgBase64: base64Photo }),
                    headers: { 'Content-Type': 'application/json' },
                    timeout: 15000
                }).then((uploadResp) => {
                    expect(uploadResp.status).to.equal(200);

                    // 3. Confirm the uploaded photo is now accessible as a static file.
                    cy.request({
                        method: 'GET',
                        url: `/Images/Person/${personId}.png`,
                        failOnStatusCode: false,
                        timeout: 10000
                    }).then((beforeResp) => {
                        expect(beforeResp.status).to.equal(200,
                            `Photo should be accessible at /Images/Person/${personId}.png before reset`);

                        // 4. Navigate to the reset page then call the reset endpoint.
                        cy.visit('/admin/system/reset');
                        cy.contains('.alert-danger', 'Destructive Operation', { timeout: 15000 })
                            .should('be.visible');

                        cy.request({
                            method: 'DELETE',
                            url: '/admin/api/database/reset',
                            timeout: 60000
                        }).then((resetResp) => {
                            expect(resetResp.status).to.equal(200);
                            expect(resetResp.body).to.have.property('success', true);

                            // 5. After reset, the uploaded photo file must be gone from disk.
                            //    The static-file path does not require authentication.
                            cy.request({
                                method: 'GET',
                                url: `/Images/Person/${personId}.png`,
                                failOnStatusCode: false,
                                timeout: 10000
                            }).then((afterResp) => {
                                expect(afterResp.status).to.equal(404,
                                    `Photo /Images/Person/${personId}.png must be deleted after DB reset (GHSA-r68j-h5c6-w6gh)`);
                            });
                        });
                    });
                });
            });
        });

        it('should reset the database via API', () => {
            manualLogin();

            // Navigate to the reset page BEFORE firing the destructive API call.
            // manualLogin() leaves the browser on /v2/dashboard (or church-info),
            // which fires async widget XHRs (cart, familiesInCart,
            // deposits/dashboard, calendar counters, ...). If any are still
            // in-flight when /admin/api/database/reset invalidates the session
            // and wipes tables, they reject with unexpected payloads and
            // surface as "An unknown error has occurred: [object Object]"
            // unhandled rejections that fail the test — a pure race condition.
            //
            // The reset page is simple (one confirmation form, no DataTable,
            // no charts) — loading it replaces the dashboard, aborts any
            // pending dashboard XHRs, and gives the header cart-count refresh
            // time to complete before we fire the destructive call.
            cy.visit('/admin/system/reset');
            cy.contains('.alert-danger', 'Destructive Operation', { timeout: 15000 })
                .should('be.visible');
            cy.get('#resetBtn').should('be.disabled');

            // Browser is now idle on a static page. Perform reset via API.
            cy.request({
                method: 'DELETE',
                url: '/admin/api/database/reset',
                timeout: 60000
            }).then((response) => {
                expect(response.status).to.equal(200);
                expect(response.body).to.have.property('success', true);
                expect(response.body).to.have.property('msg');
                expect(response.body).to.have.property('defaultUsername', 'admin');
                expect(response.body).to.have.property('defaultPassword', 'changeme');

                cy.log('Database reset successful');
            });
        });
    });
});

// Post-reset verification (Steps 10c / 10d / 10e) lives in
// 05-post-reset-verification.spec.js so Cypress tears down the browser context
// between the destructive reset and the verification steps. This eliminates
// any possibility of lingering XHRs from the pre-reset session interacting
// with the freshly-wiped database.
