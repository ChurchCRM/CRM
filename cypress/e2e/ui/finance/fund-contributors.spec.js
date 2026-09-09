/**
 * Fund Summary contributor drill-down spec
 *
 * Tests the new GET /finance/fund/{fundId}/contributors page added in issue #9377.
 *
 * Strategy: derive the fund link from the live Pledge Dashboard so the test is
 * not coupled to a hard-coded fund ID. Guards skip gracefully when no fund data
 * exists in the test environment.
 */
describe('Finance: Fund Contributor Drill-down', () => {
    before(() => {
        cy.setupAdminSession();
    });

    // ------------------------------------------------------------------
    // a) Click a fund link on the Pledge Dashboard → navigate to contributors page
    // ------------------------------------------------------------------
    it('fund name link on Pledge Dashboard navigates to contributors page', () => {
        cy.visit('/finance/pledge/dashboard');
        cy.url().should('include', '/finance/pledge/dashboard');

        // If there are no linked funds, skip
        cy.get('body').then(($body) => {
            if ($body.find('.fund-summary-link').length === 0) {
                cy.log('No fund-summary-link found — skipping navigation test');
                return;
            }

            cy.get('.fund-summary-link').first().then(($link) => {
                const href = $link.attr('href');
                const fundId = $link.attr('data-fund-id');
                expect(href).to.include('/finance/fund/');
                expect(href).to.include('/contributors');

                cy.get('.fund-summary-link').first().click();
                cy.location('pathname').should('match', /\/finance\/fund\/\d+\/contributors/);
                cy.location('pathname').should('include', `/finance/fund/${fundId}/contributors`);
            });
        });
    });

    // ------------------------------------------------------------------
    // b) Contributor table renders with expected headers
    // ------------------------------------------------------------------
    it('contributors page renders with correct table headers', () => {
        cy.visit('/finance/pledge/dashboard');

        cy.get('body').then(($body) => {
            if ($body.find('.fund-summary-link').length === 0) {
                cy.log('No fund-summary-link found — skipping table header test');
                return;
            }

            cy.get('.fund-summary-link').first().click();
            cy.location('pathname').should('match', /\/finance\/fund\/\d+\/contributors/);

            // Either the DataTable is present (with data) or the empty-state alert is shown
            cy.get('body').then(($contribs) => {
                if ($contribs.find('#fundContributors').length > 0) {
                    cy.get('#fundContributors thead th').then(($headers) => {
                        const headerTexts = [...$headers].map((el) => el.textContent.trim());
                        expect(headerTexts).to.include('Family Name');
                        expect(headerTexts).to.include('Pledged Amount');
                        expect(headerTexts).to.include('Payments');
                        expect(headerTexts).to.include('Remaining');
                        expect(headerTexts).to.include('% Paid');
                        expect(headerTexts).to.include('Actions');
                    });
                } else {
                    // Empty state is also valid
                    cy.get('.alert-info').should('exist');
                    cy.log('Empty state shown — no contributors for this fund/year');
                }
            });
        });
    });

    // ------------------------------------------------------------------
    // c) Fiscal year filter changes the displayed data (URL updates with fyid=)
    // ------------------------------------------------------------------
    it('changing fiscal year selector updates URL with fyid param', () => {
        cy.visit('/finance/pledge/dashboard');

        cy.get('body').then(($body) => {
            if ($body.find('.fund-summary-link').length === 0) {
                cy.log('No fund-summary-link found — skipping FY filter test');
                return;
            }

            // Navigate to contributors page
            cy.get('.fund-summary-link').first().click();
            cy.location('pathname').should('match', /\/finance\/fund\/\d+\/contributors/);

            // The FY selector must exist
            cy.get('#fyid').should('exist');

            // Find how many options are available
            cy.get('#fyid option').then(($options) => {
                if ($options.length < 2) {
                    cy.log('Only one fiscal year available — skipping FY change test');
                    return;
                }

                // Select the last option (oldest year) to force a URL change
                const lastValue = $options.last().attr('value');
                cy.get('#fyid').select(lastValue);

                // After selection the form auto-submits via change listener
                cy.location('search').should('include', `fyid=${lastValue}`);
            });
        });
    });

    // ------------------------------------------------------------------
    // d) Contributor row View action links to /finance/pledge/{groupKey}
    // ------------------------------------------------------------------
    it('contributor view action links to the pledge detail page', () => {
        cy.visit('/finance/pledge/dashboard');

        cy.get('body').then(($body) => {
            if ($body.find('.fund-summary-link').length === 0) {
                cy.log('No fund-summary-link found — skipping contributor action test');
                return;
            }

            cy.get('.fund-summary-link').first().click();
            cy.location('pathname').should('match', /\/finance\/fund\/\d+\/contributors/);

            cy.get('body').then(($contribs) => {
                if ($contribs.find('.contributor-view-link').length === 0) {
                    cy.log('No contributor rows with a view link — skipping action test');
                    return;
                }

                cy.get('.contributor-view-link').first().then(($link) => {
                    const href = $link.attr('href');
                    expect(href).to.include('/finance/pledge/');
                });
            });
        });
    });
});

// ------------------------------------------------------------------
// Authorization: users without finance role cannot access the page
// ------------------------------------------------------------------
describe('Finance: Fund Contributor Drill-down - Authorization', () => {
    it('denies access to users without the finance role', () => {
        cy.setupNoFinanceSession();
        // Fund ID 1 is a reasonable probe; the auth check fires before any DB query
        cy.visit('/finance/fund/1/contributors', { failOnStatusCode: false });
        // FinanceRoleAuthMiddleware redirects to the access-denied page
        cy.url().should('include', 'access-denied');
    });
});

// ------------------------------------------------------------------
// 404 guard: nonexistent fund ID returns error page, not a crash
// ------------------------------------------------------------------
describe('Finance: Fund Contributor Drill-down - 404 guard', () => {
    before(() => {
        cy.setupAdminSession();
    });

    it('returns a 404-like response for a nonexistent fund ID', () => {
        cy.request({
            url: '/finance/fund/99999/contributors',
            failOnStatusCode: false,
        }).then((response) => {
            // Route returns HTTP 404 when the fund does not exist
            expect(response.status).to.eq(404);
        });
    });
});
