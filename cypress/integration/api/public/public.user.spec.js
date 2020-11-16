/// <reference types="cypress" />

context('API Public User', () => {

    it('Login', () => {
        let user = {
            "userName": "admin",
            "password": "changeme"
        };

        cy.request({
            method: 'POST',
            url: '/api/public/user/login',
            headers: {'content-type': 'application/json'},
            body: user
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result.apiKey).to.eq("ajGwpy8Pdai22XDUpqjC5Ob04v0eG7EGgb4vz2bD2juT8YDmfM");
        })
    });
});

