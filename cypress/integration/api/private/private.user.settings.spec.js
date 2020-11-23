/// <reference types="cypress" />

context('API Private Current User', () => {

    it('Set / GET Current User Settings', () => {
        let json = {"value": "skin-green"};
        cy.makePrivateAPICall("POST", '/api/user/1/setting/ui.style', json, 200);

        cy.request({
            method: 'GET',
            url: '/api/user/1/setting/ui.style',
            headers: {'content-type': 'application/json', "x-api-key": Cypress.env('admin.api.key')},
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(result.value).to.eq(json.value);
        });
    });

    it('Admin Set / GET Other User Settings', () => {
        let json = {"value": "skin-yellow-light"};
        cy.makePrivateAPICall("POST", '/api/user/95/setting/ui.style', json, 200);

        cy.request({
            method: 'GET',
            url: '/api/user/95/setting/ui.style',
            headers: {'content-type': 'application/json', "x-api-key": Cypress.env('admin.api.key')},
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(result.value).to.eq(json.value);
        });
    });

    it('Admin invalid user ', () => {
        cy.makePrivateAPICall("GET", '/api/user/99999/setting/ui.style', null, 412);
    });
});

