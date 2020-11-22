/// <reference types="cypress" />

context('API Private Current User', () => {

    it('Set / GET Current User Settings', () => {
        let json = {"value": "blue-color-xx"};
        cy.makePrivateAPICall("POST", '/api/user/current/settings/ui.style', json, 200);

        cy.request({
            method: 'GET',
            url: '/api/user/current/settings/ui.style',
            headers: {'content-type': 'application/json', "x-api-key": Cypress.env('admin.api.key')},
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result.value).to.eq(json.value);
        });
    });
});

