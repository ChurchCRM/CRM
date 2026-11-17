/// <reference types="cypress" />

import { inOwnCookieJar } from '../../support/cookie-jar';

describe('Administrator diagnoses and manages another login', () => {
    let temporaryUserCreated = false;

    after(() => {
        if (temporaryUserCreated) {
            cy.makePrivateAdminAPICall('DELETE', '/admin/api/user/25/', { disableSignIn: false }, 200);
        }
    });

    it('shows the selected user status, effective access, and permitted actions together', () => {
        cy.setupAdminSession();
        cy.visit('/admin/system/users/3');
        cy.get('[data-cy="user-username"]').should('have.text', 'tony.wade@example.com');
        cy.get('[data-cy="user-identity"] a[href$="/people/view/3"]').should('contain', 'Tony');
        cy.get('[data-cy="user-status"]').within(() => {
            cy.contains('dt', 'Failed login attempts').next('dd').should('have.text', '0');
            cy.contains('dt', 'Lockout state').next('dd').should('have.text', 'Not locked');
            cy.contains('dt', 'Password change required at next login').next('dd').should('have.text', 'No');
            cy.contains('dt', 'Two-factor authentication enrolled').next('dd').should('have.text', 'No');
            cy.contains('dt', 'Last successful login').next('dd').should('not.be.empty');
        });
        cy.get('[data-cy="user-access-level"]').should('have.text', 'Custom');
        cy.get('[data-cy="permission-row"]').should('have.length.greaterThan', 10);
        cy.get('[data-cy="permission-row"][data-granted="false"]').each((row) => {
            cy.wrap(row).find('[data-cy="permission-reason"]').invoke('text').should('not.be.empty');
        });
        cy.get('[data-cy="user-admin-actions"]').within(() => {
            cy.get('a[href$="/admin/system/users/3/edit"]').should('be.visible');
            cy.get('a[href$="/admin/system/user/3/changePassword"]').should('be.visible');
            cy.get('.js-delete-user').should('have.attr', 'data-user_id', '3');
        });
    });

    it('sets a password and deletes a temporary login from the detail actions', () => {
        cy.setupAdminSession();
        cy.visit('/admin/system/users/new?personId=25');
        cy.get('#UserName').clear().type('admin-detail-test');
        cy.get('#SaveButton').click();
        cy.visit('/admin/system/users/25');
        cy.get('[data-cy="user-username"]').should('have.text', 'admin-detail-test');
        cy.then(() => { temporaryUserCreated = true; });
        const ballotSetting = '/api/user/25/setting/notification.dismissed.d2fa1db07ef75272d4cdfba18d2';
        inOwnCookieJar(() => {
            cy.makePrivateAdminAPICall('POST', ballotSetting, { value: 'true' }, 200)
                .its('body.value').should('eq', 'true');
        });
        cy.reload();
        inOwnCookieJar(() => {
            cy.makePrivateAdminAPICall('GET', ballotSetting, null, 200)
                .its('body.value').should('eq', 'true');
        });
        cy.get('[data-cy="user-admin-actions"] a[href$="/changePassword"]').click();
        cy.get('#NewPassword1').type('Detail-Test-Password-837!');
        cy.get('#NewPassword2').type('Detail-Test-Password-837!');
        cy.get('#NewPassword1').closest('form').submit();
        cy.contains('Password Change Successful').should('be.visible');
        cy.visit('/admin/system/users/25');
        cy.contains('[data-cy="user-status"] dt', 'Password change required at next login').next('dd').should('have.text', 'No');
        cy.get('[data-cy="user-admin-actions"] .js-delete-user').click();
        cy.get('#delete-user-disable-sign-in').uncheck();
        cy.intercept('DELETE', '**/admin/api/user/25/').as('deleteUser');
        cy.get('.bootbox-accept').click();
        cy.wait('@deleteUser').its('response.statusCode').should('eq', 200);
        cy.then(() => { temporaryUserCreated = false; });
        cy.location('pathname').should('match', /\/admin\/system\/users$/);
        cy.request({ url: '/admin/system/users/25', failOnStatusCode: false }).its('status').should('eq', 404);
    });

    it('denies staff without administration access', () => {
        cy.setupPersonaSession('zero-permission-staff');
        cy.request({
            url: '/admin/system/users/3',
            headers: { Accept: 'application/json' },
            failOnStatusCode: false,
        }).its('status').should('eq', 403);
        cy.visit('/admin/system/users/3', { failOnStatusCode: false });
        cy.location('pathname').should('include', '/v2/access-denied');
    });
});
